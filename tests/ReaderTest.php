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
 * Reading is checked by a full round trip: object → JSON → object → JSON.
 *
 * Two texts matching means both that the structure was restored and that the `$ref`s
 * point where they should again: separate instances instead of one shared would give
 * written-out copies instead of references.
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
                // the multi-file cases are checked separately: a single read knows
                // nothing of the neighbours and could not resolve the references
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
     * A reference from one file into another has to give the same object as the
     * declaration: otherwise writing it back would spread it out as a copy.
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

    /**
     * Three files in a ring: a → b → c → a. The references are resolved by one registry
     * for every file, so a ring between them is as ordinary a cycle as a recursive schema.
     */
    public function testReferencesGoAroundThreeFiles(): void
    {
        $document = static fn (string $ref): string => (string) json_encode([
            'openapi' => '3.0.3',
            'info' => ['title' => 'Ring', 'version' => '1.0.0'],
            'paths' => new stdClass(),
            'components' => ['schemas' => [
                'Node' => ['type' => 'object', 'properties' => ['next' => ['$ref' => $ref]]],
            ]],
        ]);

        $contents = [
            'a.json' => $document('b.json#/components/schemas/Node'),
            'b.json' => $document('c.json#/components/schemas/Node'),
            'c.json' => $document('a.json#/components/schemas/Node'),
        ];

        $built = (new Builder(...Reader::readAll($contents)))->prepareToSave();

        foreach (['a.json' => 'b.json', 'b.json' => 'c.json', 'c.json' => 'a.json'] as $file => $next) {
            self::assertStringContainsString(
                sprintf('"$ref": "%s#/components/schemas/Node"', $next),
                (new JsonEncoder())->encode($built[$file] ?? new stdClass()),
            );
        }
    }

    /**
     * The specification allows a reference to anywhere at all, a URL included. The
     * package reads only the files it was handed, and the refusal says exactly that
     * rather than "the target was not found".
     *
     * @dataProvider provideRejectsReferenceOutsideCases
     */
    public function testRejectsReferenceOutside(string $ref): void
    {
        $this->expectException(InvalidDocumentOpenapiException::class);
        $this->expectExceptionMessage('points at a file that was not passed to the reader');

        Reader::read((string) json_encode([
            'openapi' => '3.0.3',
            'info' => ['title' => 'Outside', 'version' => '1.0.0'],
            'paths' => new stdClass(),
            'components' => ['schemas' => ['User' => ['$ref' => $ref]]],
        ]));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideRejectsReferenceOutsideCases(): iterable
    {
        yield 'by url' => ['https://example.com/schemas.json#/components/schemas/User'];

        yield 'by file name' => ['components.yaml#/components/schemas/User'];
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

        // the reference leads to the same object rather than to a copy of it
        self::assertStringContainsString(
            '"$ref": "#/components/schemas/Node"',
            (new JsonEncoder())->encode(self::build($document)),
        );
    }

    /**
     * Strict mode names whatever the package did not understand in the document:
     * otherwise it would quietly disappear when the document is written back.
     */
    public function testStrictModeNamesWhatItDidNotRead(): void
    {
        $document = (string) json_encode([
            'openapi' => '3.0.3',
            'info' => ['title' => 'x', 'version' => '1', 'slogan' => 'Fast!'],
            'paths' => new stdClass(),
            'components' => ['securitySchemes' => ['bearer' => [
                'type' => 'http',
                'scheme' => 'bearer',
                // "Applies To" in the specification: in is declared for apiKey only
                'in' => 'header',
            ]]],
        ]);

        try {
            Reader::read($document);
            self::fail('Strict reading was expected to complain.');
        } catch (InvalidDocumentOpenapiException $exception) {
            self::assertStringContainsString('openapi.json/info/slogan', $exception->getMessage());
            self::assertStringContainsString('openapi.json/components/securitySchemes/bearer/in', $exception->getMessage());
            self::assertStringContainsString('strict: false', $exception->getMessage());
        }

        // the same without strictness: the fields are dropped, the document is read
        $built = self::build(Reader::read($document, null, strict: false));

        self::assertEquals(
            json_decode((string) json_encode(['securitySchemes' => ['bearer' => ['type' => 'http', 'scheme' => 'bearer']]])),
            $built->components ?? null,
        );
    }

    /**
     * A keyword that was read and dropped does not count as misunderstood in strict mode:
     * it changes nothing, and the package knows why.
     *
     * @dataProvider provideStrictModeAcceptsDroppedKeywordsCases
     *
     * @param array<string, mixed> $document
     */
    public function testStrictModeAcceptsDroppedKeywords(array $document): void
    {
        Reader::read((string) json_encode($document));

        $this->expectNotToPerformAssertions();
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideStrictModeAcceptsDroppedKeywordsCases(): iterable
    {
        yield 'an assertion about another type' => [self::documentWithComponents('3.0.3', [
            'schemas' => ['Name' => ['type' => 'string', 'uniqueItems' => true, 'minItems' => 1]],
        ])];

        yield 'siblings of $ref in 3.0' => [self::documentWithComponents('3.0.3', [
            'schemas' => [
                'User' => ['type' => 'object'],
                'Author' => ['$ref' => '#/components/schemas/User', 'type' => 'object', 'x-role' => ['of' => 'author']],
            ],
        ])];

        yield 'an example beside an enum' => [self::documentWithComponents('3.0.3', [
            'schemas' => ['Kind' => ['type' => 'string', 'enum' => ['a', 'b'], 'example' => 'a']],
        ])];

        yield 'encoding on a media type that ignores it' => [self::documentWithComponents('3.1.0', [
            'requestBodies' => ['Upload' => ['content' => [
                'application/json' => ['encoding' => ['file' => ['contentType' => 'image/png']]],
            ]]],
        ])];

        yield 'an assertion on a schema without a type' => [self::documentWithComponents('3.0.3', [
            'schemas' => ['Either' => ['minLength' => 3, 'minItems' => 1]],
        ])];
    }

    public function testRejectsRequiredFalseOnAPathParameter(): void
    {
        $this->expectException(InvalidDocumentOpenapiException::class);
        $this->expectExceptionMessage('a path parameter is always required');

        Reader::read((string) json_encode([
            'openapi' => '3.0.3',
            'info' => ['title' => 'x', 'version' => '1'],
            'paths' => ['/users/{id}' => ['get' => [
                'parameters' => [['name' => 'id', 'in' => 'path', 'required' => false, 'schema' => ['type' => 'string']]],
                'responses' => ['200' => ['description' => 'OK']],
            ]]],
        ]));
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
     * An enumeration is read by its meaning: whatever changes nothing is dropped, and a
     * composition is kept as an equivalent allOf.
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
     * The specification requires the scheme's name to be declared in
     * components.securitySchemes but does not require the scopes themselves to be
     * declared: with openIdConnect there is nowhere to declare them, and an oauth2
     * document may require a scope that no flow has.
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
                // in 3.0 items is required only when type: array is declared
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
     * @param array<string, mixed> $components
     *
     * @return array<string, mixed>
     */
    private static function documentWithComponents(string $version, array $components): array
    {
        return [
            'openapi' => $version,
            'info' => ['title' => 'Dropped', 'version' => '1.0.0'],
            'paths' => new stdClass(),
            'components' => $components,
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
