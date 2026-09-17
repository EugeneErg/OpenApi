<?php

declare(strict_types = 1);

namespace Tests;

use EugeneErg\OpenApi\Builder;
use EugeneErg\OpenApi\Exceptions\InvalidDocumentOpenapiException;
use EugeneErg\OpenApi\Openapi;
use EugeneErg\OpenApi\Reader;
use EugeneErg\OpenApi\Serialization\JsonEncoder;
use PHPUnit\Framework\TestCase;
use stdClass;
use UnexpectedValueException;

use function count;
use function is_array;
use function is_string;
use function sprintf;

/**
 * Разбор проверяется полным кругом: объект → JSON → объект → JSON.
 *
 * Совпадение двух текстов означает и то, что структура восстановлена, и то,
 * что `$ref` снова указывают куда надо: разные экземпляры вместо общего дали бы
 * развёрнутые копии вместо ссылок.
 */
final class ReaderTest extends TestCase
{
    /**
     * @dataProvider provideDocumentSurvivesRoundTripCases
     */
    public function testDocumentSurvivesRoundTrip(string $json): void
    {
        self::assertSame($json, (new JsonEncoder())->encode(self::build(Reader::read($json))));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideDocumentSurvivesRoundTripCases(): iterable
    {
        foreach (glob(__DIR__ . '/Cases/BuilderTest/Objects/*.php') ?: [] as $objectPath) {
            $documents = self::load($objectPath);

            foreach ($documents as $fileName => $document) {
                // мультифайловые кейсы проверяются отдельно: одиночное чтение
                // не знает о соседях и не смогло бы разрешить ссылки между файлами
                if (count($documents) > 1) {
                    continue;
                }

                yield basename($objectPath, '.php') => [
                    (new JsonEncoder())->encode(self::build($document, $fileName)),
                ];
            }
        }
    }

    /**
     * Ссылка из одного файла в другой обязана дать тот же объект, что и объявление:
     * иначе при обратной записи она развернулась бы копией.
     */
    public function testCrossFileReferencesShareObjects(): void
    {
        $documents = self::load(__DIR__ . '/Cases/BuilderTest/Objects/components-in-outside.php');
        $built = (new Builder(...$documents))->prepareToSave();
        $contents = [];

        foreach ($built as $fileName => $document) {
            $contents[$fileName] = (new JsonEncoder())->encode($document);
        }

        $again = (new Builder(...Reader::readAll($contents)))->prepareToSave();

        foreach ($built as $fileName => $document) {
            self::assertSame(
                (new JsonEncoder())->encode($document),
                (new JsonEncoder())->encode($again[$fileName] ?? new stdClass()),
            );
        }
    }

    public function testRecursiveSchemaBecomesACycle(): void
    {
        $document = Reader::read((string) json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'Tree', 'version' => '1.0.0'],
            'paths' => new stdClass(),
            'components' => ['schemas' => ['Node' => [
                'type' => 'object',
                'properties' => ['next' => ['$ref' => '#/components/schemas/Node']],
            ]]],
        ]));

        self::assertArrayHasKey('Node', $document->components->schemas->items);

        // ссылка ведёт на тот же объект, а не на его копию
        self::assertStringContainsString(
            '"$ref": "#/components/schemas/Node"',
            (new JsonEncoder())->encode(self::build($document)),
        );
    }

    public function testRejectsUnknownReference(): void
    {
        $this->expectException(InvalidDocumentOpenapiException::class);
        $this->expectExceptionMessage('does not point at anything');

        Reader::read((string) json_encode([
            'openapi' => '3.0.3',
            'info' => ['title' => 'x', 'version' => '1'],
            'paths' => new stdClass(),
            'components' => ['schemas' => ['A' => ['$ref' => '#/components/schemas/Missing']]],
        ]));
    }

    public function testRejectsUnsupportedVersion(): void
    {
        $this->expectException(InvalidDocumentOpenapiException::class);
        $this->expectExceptionMessage('a supported OpenAPI version');

        Reader::read((string) json_encode([
            'openapi' => '2.0',
            'info' => ['title' => 'x', 'version' => '1'],
            'paths' => new stdClass(),
        ]));
    }

    /**
     * Перечисление читается по смыслу: то, что ничего не меняет, отбрасывается,
     * композиция сохраняется эквивалентным allOf.
     *
     * @dataProvider provideEnumIsReadByMeaningCases
     *
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $expected
     */
    public function testEnumIsReadByMeaning(string $version, array $schema, array $expected): void
    {
        $built = self::build(Reader::read(self::documentWith($version, $schema)));

        self::assertEquals(
            json_decode((string) json_encode(['schemas' => ['S' => $expected]])),
            $built->components ?? null,
        );
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, array<string, mixed>}>
     */
    public static function provideEnumIsReadByMeaningCases(): iterable
    {
        yield 'nullable without null in enum means nullable' => [
            '3.0.3',
            ['type' => 'string', 'nullable' => true, 'enum' => ['a', 'b']],
            ['type' => 'string', 'nullable' => true, 'enum' => ['a', 'b', null]],
        ];

        yield 'null in enum makes it nullable' => [
            '3.0.3',
            ['type' => 'string', 'nullable' => true, 'enum' => ['a', null]],
            ['type' => 'string', 'nullable' => true, 'enum' => ['a', null]],
        ];

        yield '3.1 nullable enum' => [
            '3.1.0',
            ['type' => ['string', 'null'], 'enum' => ['a', null]],
            ['type' => ['string', 'null'], 'enum' => ['a', null]],
        ];

        yield 'assertions satisfied by every value are dropped' => [
            '3.0.3',
            ['type' => 'string', 'maxLength' => 5000, 'pattern' => '^[a-z]+$', 'enum' => ['card', 'bank']],
            ['type' => 'string', 'enum' => ['card', 'bank']],
        ];

        yield 'example and inapplicable keywords are dropped' => [
            '3.0.3',
            ['type' => 'string', 'minItems' => 1, 'example' => 'nope', 'enum' => ['push']],
            ['type' => 'string', 'enum' => ['push']],
        ];

        yield 'annotations are kept' => [
            '3.0.3',
            ['type' => 'string', 'format' => 'http-method', 'readOnly' => true, 'default' => 'GET', 'enum' => ['GET', 'POST']],
            ['type' => 'string', 'format' => 'http-method', 'readOnly' => true, 'default' => 'GET', 'enum' => ['GET', 'POST']],
        ];

        yield 'const together with enum is their intersection' => [
            '3.1.0',
            ['type' => 'string', 'const' => 'a', 'enum' => ['a', 'b']],
            ['type' => 'string', 'const' => 'a'],
        ];

        yield 'single value in 3.1 is const' => [
            '3.1.0',
            ['type' => 'string', 'enum' => ['account']],
            ['type' => 'string', 'const' => 'account'],
        ];

        yield 'repeated values are merged' => [
            '3.0.3',
            ['type' => 'integer', 'enum' => [1, 1, 2.0]],
            ['type' => 'integer', 'enum' => [1, 2]],
        ];

        yield 'both booleans restrict nothing' => [
            '3.0.3',
            ['type' => 'boolean', 'description' => 'Flag.', 'enum' => [true, false]],
            ['type' => 'boolean', 'description' => 'Flag.'],
        ];

        yield 'type is inferred from the values' => [
            '3.0.3',
            ['enum' => [1, 2]],
            ['type' => 'integer', 'enum' => [1, 2]],
        ];

        yield 'values of different types stay untyped' => [
            '3.0.3',
            ['enum' => ['auto', 0, null]],
            ['enum' => ['auto', 0, null]],
        ];

        yield 'composition is kept through allOf' => [
            '3.0.3',
            ['type' => 'string', 'description' => 'Narrowed.', 'not' => ['type' => 'string', 'maxLength' => 0], 'enum' => ['a']],
            ['allOf' => [
                ['type' => 'string', 'description' => 'Narrowed.', 'not' => ['type' => 'string', 'maxLength' => 0]],
                ['type' => 'string', 'enum' => ['a']],
            ]],
        ];
    }

    /**
     * Спецификация требует, чтобы имя схемы было объявлено в components.securitySchemes,
     * но объявлять сами скоупы не обязывает: у openIdConnect их негде объявить,
     * а oauth2-документ может требовать скоуп, которого нет ни в одном flow.
     *
     * @dataProvider provideScopeNamesNeedNoDeclarationCases
     *
     * @param array<string, mixed> $scheme
     */
    public function testScopeNamesNeedNoDeclaration(array $scheme, string $scope): void
    {
        $document = (string) json_encode([
            'openapi' => '3.0.3',
            'info' => ['title' => 'Security', 'version' => '1.0.0'],
            'paths' => new stdClass(),
            'security' => [['auth' => [$scope]]],
            'components' => ['securitySchemes' => ['auth' => $scheme]],
        ]);

        self::assertEquals(
            json_decode((string) json_encode([['auth' => [$scope]]])),
            self::build(Reader::read($document))->security ?? null,
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideScopeNamesNeedNoDeclarationCases(): iterable
    {
        yield 'oauth2 scope outside every flow' => [
            [
                'type' => 'oauth2',
                'flows' => ['clientCredentials' => ['tokenUrl' => 'https://example.com/token', 'scopes' => []]],
            ],
            'goals:write',
        ];

        yield 'openIdConnect scope' => [
            ['type' => 'openIdConnect', 'openIdConnectUrl' => 'https://example.com/.well-known'],
            'openid',
        ];
    }

    /**
     * @dataProvider provideContradictoryEnumIsRejectedCases
     *
     * @param array<string, mixed> $schema
     */
    public function testContradictoryEnumIsRejected(string $version, array $schema, string $message): void
    {
        $this->expectException(InvalidDocumentOpenapiException::class);
        $this->expectExceptionMessage($message);

        Reader::read(self::documentWith($version, $schema));
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, string}>
     */
    public static function provideContradictoryEnumIsRejectedCases(): iterable
    {
        yield 'value excluded by maxLength' => [
            '3.0.3',
            ['type' => 'string', 'maxLength' => 3, 'enum' => ['ok', 'too long']],
            'excluded by "maxLength"',
        ];

        yield 'value excluded by minimum' => [
            '3.1.0',
            ['type' => 'integer', 'exclusiveMinimum' => 0, 'enum' => [0, 1]],
            'excluded by "exclusiveMinimum"',
        ];

        yield 'null without nullable' => [
            '3.0.3',
            ['type' => 'string', 'enum' => ['a', null]],
            'does not allow it',
        ];

        yield 'value of another type' => [
            '3.0.3',
            ['type' => 'integer', 'enum' => [1, 'two']],
            'not of type "integer"',
        ];

        yield 'const outside enum' => [
            '3.1.0',
            ['const' => 'c', 'enum' => ['a', 'b']],
            'const is not one of the enum values',
        ];

        yield 'default outside enum' => [
            '3.0.3',
            ['type' => 'string', 'default' => 'c', 'enum' => ['a', 'b']],
            'default "c" is not one of the enum values',
        ];
    }

    /**
     * @dataProvider provideComponentsAreReadByMeaningCases
     *
     * @param array<string, mixed> $components
     * @param array<string, mixed> $expected
     */
    public function testComponentsAreReadByMeaning(string $version, array $components, array $expected): void
    {
        $document = (string) json_encode([
            'openapi' => $version,
            'info' => ['title' => 'Components', 'version' => '1.0.0'],
            'paths' => new stdClass(),
            'components' => $components,
        ]);

        self::assertEquals(
            json_decode((string) json_encode($expected)),
            self::build(Reader::read($document))->components ?? null,
        );
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, array<string, mixed>}>
     */
    public static function provideComponentsAreReadByMeaningCases(): iterable
    {
        $user = ['type' => 'object', 'properties' => ['id' => ['type' => 'string']]];

        yield 'alias keeps its own name' => [
            '3.0.3',
            ['schemas' => [
                'UserCompact' => $user,
                'UserBase' => ['$ref' => '#/components/schemas/UserCompact'],
                'Like' => ['type' => 'object', 'properties' => ['user' => ['$ref' => '#/components/schemas/UserBase']]],
            ]],
            ['schemas' => [
                'UserCompact' => $user,
                'UserBase' => ['$ref' => '#/components/schemas/UserCompact'],
                'Like' => ['type' => 'object', 'properties' => ['user' => ['$ref' => '#/components/schemas/UserBase']]],
            ]],
        ];

        yield 'siblings of $ref mean nothing in 3.0' => [
            '3.0.3',
            ['schemas' => [
                'User' => $user,
                'Post' => ['type' => 'object', 'properties' => [
                    'author' => ['$ref' => '#/components/schemas/User', 'description' => 'Ignored.', 'readOnly' => true],
                ]],
            ]],
            ['schemas' => [
                'User' => $user,
                'Post' => ['type' => 'object', 'properties' => ['author' => ['$ref' => '#/components/schemas/User']]],
            ]],
        ];

        yield 'siblings of $ref are kept in 3.1' => [
            '3.1.0',
            ['schemas' => [
                'User' => $user,
                'Post' => ['type' => 'object', 'properties' => [
                    'author' => ['$ref' => '#/components/schemas/User', 'description' => 'Author.', 'readOnly' => true],
                ]],
            ]],
            ['schemas' => [
                'User' => $user,
                'Post' => ['type' => 'object', 'properties' => [
                    'author' => [
                        'description' => 'Author.',
                        'readOnly' => true,
                        'allOf' => [['$ref' => '#/components/schemas/User']],
                    ],
                ]],
            ]],
        ];

        yield 'required names outside properties' => [
            '3.0.3',
            ['schemas' => ['Choice' => ['oneOf' => [
                ['required' => ['id']],
                ['required' => ['number', 'repo']],
            ]]]],
            ['schemas' => ['Choice' => ['oneOf' => [
                ['required' => ['id']],
                ['required' => ['number', 'repo']],
            ]]]],
        ];

        yield 'null is a value' => [
            '3.0.3',
            [
                'examples' => ['Empty' => ['value' => null]],
                'schemas' => ['Note' => ['type' => 'string', 'nullable' => true, 'default' => null]],
            ],
            [
                'examples' => ['Empty' => ['value' => null]],
                'schemas' => ['Note' => ['type' => 'string', 'nullable' => true, 'default' => null]],
            ],
        ];

        yield 'example of another type illustrates nothing' => [
            '3.0.3',
            ['schemas' => ['Ids' => ['type' => 'string', 'example' => 521621]]],
            ['schemas' => ['Ids' => ['type' => 'string']]],
        ];

        yield 'extensions are read everywhere they are allowed' => [
            '3.0.3',
            [
                'schemas' => ['Name' => ['type' => 'string', 'x-twilio' => ['pii' => ['handling' => 'standard']]]],
                'x-generated' => true,
            ],
            [
                'schemas' => ['Name' => ['type' => 'string', 'x-twilio' => ['pii' => ['handling' => 'standard']]]],
                'x-generated' => true,
            ],
        ];

        yield 'large integer bounds keep their value' => [
            '3.1.0',
            ['schemas' => ['Permissions' => [
                'type' => 'integer',
                'format' => 'int64',
                'minimum' => 0,
                'maximum' => 9223372036854775807,
            ]]],
            ['schemas' => ['Permissions' => [
                'type' => 'integer',
                'format' => 'int64',
                'minimum' => 0,
                'maximum' => 9223372036854775807,
            ]]],
        ];

        yield 'default survives on a schema without a type' => [
            '3.1.0',
            ['schemas' => ['Anything' => ['description' => 'Any value.', 'default' => ['a' => 1]]]],
            ['schemas' => ['Anything' => ['description' => 'Any value.', 'default' => ['a' => 1]]]],
        ];

        yield 'an empty enum accepts nothing' => [
            '3.1.0',
            ['schemas' => ['Nothing' => ['type' => 'string', 'enum' => []]]],
            ['schemas' => ['Nothing' => ['type' => 'string', 'not' => new stdClass()]]],
        ];

        yield 'the null type is a schema of its own' => [
            '3.1.0',
            ['schemas' => ['Nothing' => ['type' => 'null', 'description' => 'Always null.']]],
            ['schemas' => ['Nothing' => ['type' => 'null', 'description' => 'Always null.']]],
        ];

        yield 'several types become anyOf of typed schemas' => [
            '3.1.0',
            ['schemas' => ['Id' => [
                'type' => ['string', 'integer'],
                'description' => 'Identifier.',
                'maxLength' => 5,
                'minimum' => 0,
            ]]],
            ['schemas' => ['Id' => ['allOf' => [
                ['description' => 'Identifier.'],
                ['anyOf' => [
                    ['type' => 'string', 'maxLength' => 5],
                    ['type' => 'integer', 'minimum' => 0],
                ]],
            ]]]],
        ];

        yield 'null among several types joins the union' => [
            '3.1.0',
            ['schemas' => ['Id' => ['type' => ['string', 'integer', 'null']]]],
            ['schemas' => ['Id' => ['anyOf' => [
                ['type' => 'string'],
                ['type' => 'integer'],
                ['type' => 'null'],
            ]]]],
        ];

        yield 'a single type beside null stays nullable' => [
            '3.1.0',
            ['schemas' => ['Name' => ['type' => ['string', 'null'], 'maxLength' => 3]]],
            ['schemas' => ['Name' => ['type' => ['string', 'null'], 'maxLength' => 3]]],
        ];

        yield 'format is an annotation on any type' => [
            '3.0.3',
            ['schemas' => [
                'Uris' => ['type' => 'object', 'format' => 'uri-map'],
                'Caps' => ['type' => 'array', 'items' => ['type' => 'string'], 'format' => 'capabilities'],
                'Flag' => ['type' => 'boolean', 'format' => 'tri-state'],
            ]],
            ['schemas' => [
                'Uris' => ['type' => 'object', 'format' => 'uri-map'],
                'Caps' => ['type' => 'array', 'items' => ['type' => 'string'], 'format' => 'capabilities'],
                'Flag' => ['type' => 'boolean', 'format' => 'tri-state'],
            ]],
        ];

        yield 'x-x- and reserved prefixes are legal names' => [
            '3.1.0',
            ['schemas' => ['Name' => ['type' => 'string', 'x-x-legacy' => 1, 'x-oai-defined' => 'ok']]],
            ['schemas' => ['Name' => ['type' => 'string', 'x-x-legacy' => 1, 'x-oai-defined' => 'ok']]],
        ];

        yield 'a component may be named like an extension' => [
            '3.0.3',
            ['headers' => ['x-common-marker-version' => ['schema' => ['type' => 'string']]]],
            ['headers' => ['x-common-marker-version' => ['schema' => ['type' => 'string']]]],
        ];

        yield 'assertions live on a schema without a type' => [
            '3.0.3',
            ['schemas' => [
                'Long' => ['minLength' => 3, 'pattern' => '^a'],
                'Even' => ['multipleOf' => 2],
                // в 3.0 items обязателен только при объявленном type: array
                'Set' => ['uniqueItems' => true],
            ]],
            ['schemas' => [
                'Long' => ['minLength' => 3, 'pattern' => '^a'],
                'Even' => ['multipleOf' => 2],
                'Set' => ['uniqueItems' => true],
            ]],
        ];

        yield 'assertions about different types become allOf' => [
            '3.0.3',
            ['schemas' => ['Either' => ['minLength' => 3, 'minItems' => 1]]],
            ['schemas' => ['Either' => ['allOf' => [
                ['minLength' => 3],
                ['minItems' => 1],
            ]]]],
        ];

        yield 'an assertion about another type is dropped' => [
            '3.0.3',
            ['schemas' => ['Name' => ['type' => 'string', 'uniqueItems' => true, 'minItems' => 1]]],
            ['schemas' => ['Name' => ['type' => 'string']]],
        ];

        yield 'encoding keeps only what is set' => [
            '3.1.0',
            ['requestBodies' => ['Upload' => ['content' => [
                'multipart/form-data' => ['encoding' => ['file' => ['contentType' => 'image/png']]],
                'application/json' => ['encoding' => ['file' => ['contentType' => 'image/png']]],
            ]]]],
            ['requestBodies' => ['Upload' => ['content' => [
                'multipart/form-data' => ['encoding' => ['file' => ['contentType' => 'image/png']]],
                'application/json' => new stdClass(),
            ]]]],
        ];
    }

    /**
     * @param array<string, mixed> $schema
     */
    private static function documentWith(string $version, array $schema): string
    {
        return (string) json_encode([
            'openapi' => $version,
            'info' => ['title' => 'Enum', 'version' => '1.0.0'],
            'paths' => new stdClass(),
            'components' => ['schemas' => ['S' => $schema]],
        ]);
    }

    private static function build(Openapi $document, string $fileName = 'a.json'): stdClass
    {
        $result = (new Builder(...[$fileName => $document]))->prepareToSave();

        return $result[$fileName] ?? throw new UnexpectedValueException('Builder returned nothing.');
    }

    /**
     * @return array<string, Openapi>
     */
    private static function load(string $path): array
    {
        $documents = require $path;

        if (!is_array($documents)) {
            throw new UnexpectedValueException(sprintf('Case "%s" must return an array.', $path));
        }

        $result = [];

        foreach ($documents as $fileName => $document) {
            if (!is_string($fileName) || !$document instanceof Openapi) {
                throw new UnexpectedValueException(sprintf('Case "%s" returned an unexpected value.', $path));
            }

            $result[$fileName] = $document;
        }

        return $result;
    }
}
