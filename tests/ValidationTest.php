<?php

declare(strict_types = 1);

namespace Tests;

use Closure;
use EugeneErg\OpenApi\Builder;
use EugeneErg\OpenApi\Components;
use EugeneErg\OpenApi\Components\Examples;
use EugeneErg\OpenApi\Components\Examples\Example;
use EugeneErg\OpenApi\Components\Parameters;
use EugeneErg\OpenApi\Components\RequestBodies;
use EugeneErg\OpenApi\Components\Responses;
use EugeneErg\OpenApi\Components\Schemas;
use EugeneErg\OpenApi\Components\SecuritySchemes;
use EugeneErg\OpenApi\Exceptions\OpenapiExceptionInterface;
use EugeneErg\OpenApi\Extensions;
use EugeneErg\OpenApi\Info;
use EugeneErg\OpenApi\Openapi;
use EugeneErg\OpenApi\PathItems;
use EugeneErg\OpenApi\Paths;
use EugeneErg\OpenApi\Reader;
use EugeneErg\OpenApi\Reference;
use EugeneErg\OpenApi\Securities;
use EugeneErg\OpenApi\Version;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * The states the types cannot express but which have to be unreachable all the same.
 *
 * Every one of them throws an exception of the package, so a single
 * OpenapiExceptionInterface catches them all.
 */
final class ValidationTest extends TestCase
{
    /**
     * @dataProvider provideRejectsInvalidDocumentCases
     *
     * @param Closure(): mixed $build
     */
    public function testRejectsInvalidDocument(Closure $build, string $expectedMessage): void
    {
        $this->expectException(OpenapiExceptionInterface::class);
        $this->expectExceptionMessage($expectedMessage);

        $build();
    }

    /**
     * @return iterable<string, array{Closure(): mixed, string}>
     */
    public static function provideRejectsInvalidDocumentCases(): iterable
    {
        yield 'path without a leading slash' => [
            static fn () => new Paths(...['users' => new Paths\Path(get: self::operation())]),
            'must start with a slash',
        ];

        yield 'repeated template variable' => [
            static fn () => new Paths(...['/a/{id}/b/{id}' => new Paths\Path(get: self::operation())]),
            'same template variable more than once',
        ];

        yield 'template variable without a parameter' => [
            static fn () => self::document(new Paths(...[
                '/users/{id}' => new Paths\Path(get: self::operation()),
            ])),
            'has no path parameter for {id}',
        ];

        yield 'parameter without a template variable' => [
            static fn () => self::document(new Paths(...[
                '/users' => new Paths\Path(get: self::operation(self::pathParameters())),
            ])),
            'does not appear in the template',
        ];

        yield 'the same parameter listed twice' => [
            static function (): array {
                $page = new Parameters\Query\SchemaParameter(schema: new Schemas\Integer\Schema());

                $document = self::document(
                    new Paths(...['/users' => new Paths\Path(get: new Paths\Operation(
                        responses: new Responses(x200: new Responses\Response(description: 'OK')),
                        parameters: new Parameters\Parameters(
                            // one and the same parameter: by name and without a name, through a component
                            queries: new Parameters\Query\Queries(...[$page, 'page' => $page]),
                        ),
                    ))]),
                    new Components(parameters: new Parameters(page: new Parameters\Parameter(name: 'page', parameter: $page))),
                );

                return (new Builder(...['api.json' => $document]))->prepareToSave();
            },
            'unique by its name and location',
        ];

        yield 'operation without responses' => [
            static fn () => new Paths\Operation(responses: new Responses()),
            'at least one response',
        ];

        yield 'webhooks on 3.0' => [
            static fn () => new Openapi(
                info: self::info(),
                version: Version::V303,
                webhooks: new PathItems(...['a' => new Paths\Path()]),
            ),
            'introduced in OpenAPI 3.1',
        ];

        yield 'string length range inverted' => [
            static fn () => new Schemas\String\Schema(minLength: 10, maxLength: 3),
            'cannot be greater than upper bound',
        ];

        yield 'numeric range inverted' => [
            static fn () => new Schemas\Integer\Schema(minimum: 100, maximum: 1),
            'cannot be greater than upper bound',
        ];

        yield 'multipleOf is not positive' => [
            static fn () => new Schemas\Integer\Schema(multipleOf: 0),
            'must be greater than zero',
        ];

        yield 'discriminator without composition' => [
            static fn () => new Schemas\Untyped\Schema(
                discriminator: new Schemas\Abstract\Discriminator(propertyName: 'type'),
            ),
            'only meaningful together with oneOf',
        ];

        yield 'example and externalValue together' => [
            static fn () => new Example(
                value: new Schemas\String\Value('a'),
                externalValue: 'https://example.com/a.json',
            ),
            'mutually exclusive',
        ];

        yield 'media type with both example and examples' => [
            static fn () => new RequestBodies\Content(
                schema: new Schemas\String\Schema(),
                example: new Schemas\String\Value('a'),
                examples: new Examples(a: new Example(value: new Schemas\String\Value('a'))),
            ),
            'mutually exclusive',
        ];

        yield 'reference to an unregistered component' => [
            static function (): array {
                $document = self::document(new Paths(...[
                    '/users' => new Paths\Path(
                        get: new Paths\Operation(
                            responses: new Responses(
                                x404: new Reference(new Responses\Response(description: 'Missing.')),
                            ),
                        ),
                    ),
                ]));

                return (new Builder(...['api.json' => $document]))->prepareToSave();
            },
            'is not registered in components',
        ];

        yield 'builder without file names' => [
            static fn () => new Builder(self::document(new Paths())),
            'file name as the array key',
        ];

        yield 'empty enum' => [
            static fn () => new Schemas\String\EnumSchema(new Schemas\String\Strings()),
            'at least one value',
        ];

        yield 'repeated enum value' => [
            static fn () => new Schemas\String\EnumSchema(new Schemas\String\Strings('a', 'a')),
            'more than once',
        ];

        yield '1 and 1.0 are the same enum value' => [
            static fn () => new Schemas\Number\EnumSchema(new Schemas\Number\Numbers(1, 1.0)),
            'more than once',
        ];

        yield 'objects equal regardless of key order' => [
            static fn () => new Schemas\Object\EnumSchema(new Schemas\Object\Objects(
                new Schemas\Object\OpenapiObject(a: 1, b: 2),
                new Schemas\Object\OpenapiObject(b: 2, a: 1),
            )),
            'more than once',
        ];

        yield 'null listed instead of nullable' => [
            static fn () => new Schemas\Untyped\EnumSchema(new Schemas\Untyped\Values('a', null)),
            'set nullable: true',
        ];

        yield 'default outside enum' => [
            static fn () => new Schemas\String\EnumSchema(
                new Schemas\String\Strings('a'),
                default: new Schemas\String\Value('b'),
            ),
            'is not one of the enum values',
        ];

        yield 'null default without nullable' => [
            static fn () => new Schemas\String\EnumSchema(
                new Schemas\String\Strings('a'),
                default: new Schemas\String\Value(null),
            ),
            'requires nullable',
        ];

        yield 'enum value violates date format' => [
            static fn () => new Schemas\String\EnumSchema(
                new Schemas\String\Strings('2024-02-30'),
                format: Schemas\String\Format::Date,
            ),
            'does not match format "date"',
        ];

        yield 'enum value does not fit int32' => [
            static fn () => new Schemas\Integer\EnumSchema(
                new Schemas\Integer\Integers(5_000_000_000),
                format: Schemas\Integer\Format::Int32,
            ),
            'does not fit format "int32"',
        ];

        yield 'untyped enum of a single type' => [
            static fn () => new Schemas\Untyped\EnumSchema(new Schemas\Untyped\Values('a', 'b')),
            'use the typed EnumSchema',
        ];

        yield 'integers and floats are one JSON type' => [
            static fn () => new Schemas\Untyped\EnumSchema(new Schemas\Untyped\Values(1, 2.5)),
            'of type number',
        ];

        yield 'integer-like name spread into a map' => [
            static fn () => new Schemas\Object\Properties(...['7' => self::property()]),
            'Properties::fromArray()',
        ];

        yield 'positional schema among component schemas' => [
            static fn () => new Components(schemas: new Schemas\Untyped\Schemas(new Schemas\String\Schema())),
            'components.schemas needs schemas by name',
        ];

        yield 'named schema in allOf' => [
            static fn () => new Schemas\Untyped\Schema(
                allOf: new Schemas\Untyped\Schemas(Base: new Schemas\String\Schema()),
            ),
            'allOf is a list of schemas',
        ];

        yield 'named item in a list' => [
            static fn () => new Schemas\String\Strings(first: 'a'),
            'Strings is a list',
        ];

        yield 'invalid component name' => [
            static fn () => new Components(schemas: Schemas\Untyped\Schemas::fromArray([
                'User Profile' => new Schemas\String\Schema(),
            ])),
            'must match',
        ];

        yield 'response key that is not a status code' => [
            static fn () => new Paths\Operation(responses: new Responses(ok: new Responses\Response(description: 'OK'))),
            'neither an HTTP status code',
        ];

        yield 'encoding for JSON content' => [
            static fn () => new RequestBodies\Contents(...[
                'application/json' => new RequestBodies\Content(
                    encoding: new RequestBodies\Encodings(file: new RequestBodies\Encoding(contentType: 'image/png')),
                ),
            ]),
            'only to multipart and application/x-www-form-urlencoded',
        ];

        yield 'unregistered parameter without a name' => [
            static function (): array {
                $document = self::document(new Paths(...[
                    '/users' => new Paths\Path(get: new Paths\Operation(
                        responses: new Responses(x200: new Responses\Response(description: 'OK')),
                        parameters: new Parameters\Parameters(
                            queries: new Parameters\Query\Queries(
                                new Parameters\Query\SchemaParameter(schema: new Schemas\String\Schema()),
                            ),
                        ),
                    )),
                ]));

                return (new Builder(...['api.json' => $document]))->prepareToSave();
            },
            'not registered in components.parameters',
        ];

        yield 'required name that is described in properties' => [
            static fn () => new Schemas\Object\Schema(
                properties: new Schemas\Object\Properties(id: self::property()),
                required: new Schemas\String\Strings('id'),
            ),
            'Property(required: true)',
        ];

        yield 'required name forbidden by additionalProperties' => [
            static fn () => new Schemas\Object\Schema(
                additionalProperties: false,
                required: new Schemas\String\Strings('id'),
            ),
            'can never be present',
        ];

        yield 'repeated required name' => [
            static fn () => new Schemas\Object\Schema(required: new Schemas\String\Strings('id', 'id')),
            'more than once',
        ];

        yield 'requirement naming an undeclared scheme' => [
            static fn () => Reader::read((string) json_encode([
                'openapi' => '3.0.3',
                'info' => ['title' => 'x', 'version' => '1'],
                'paths' => new stdClass(),
                'security' => [['missing' => []]],
            ])),
            'security scheme "missing" is not declared',
        ];

        yield 'link to an operation outside the document' => [
            static function (): array {
                $orphan = new Paths\Operation(
                    responses: new Responses(x200: new Responses\Response(description: 'OK')),
                );
                $document = self::document(new Paths(...['/users' => new Paths\Path(
                    get: new Paths\Operation(
                        responses: new Responses(x200: new Responses\Response(
                            description: 'OK',
                            links: new Components\Links(next: new Components\Links\Link(
                                operation: new Paths\DeferredOperation(
                                    static fn (): Paths\Operation => $orphan,
                                ),
                            )),
                        )),
                    ),
                )]));

                return (new Builder(...['api.json' => $document]))->prepareToSave();
            },
            'not registered in any document',
        ];

        // A Link may name its target by operationId instead of a pointer, and then the
        // written document says nothing about where that operation is. The specification
        // requires the id to be resolvable, so a dangling one is a broken document rather
        // than a link somebody will notice later.
        yield 'link naming an operationId that is not in the document' => [
            static function (): array {
                $orphan = new Paths\Operation(
                    responses: new Responses(x200: new Responses\Response(description: 'OK')),
                    id: 'orphan',
                );
                $document = self::document(new Paths(...['/users' => new Paths\Path(
                    get: new Paths\Operation(
                        responses: new Responses(x200: new Responses\Response(
                            description: 'OK',
                            links: new Components\Links(next: new Components\Links\Link(operation: $orphan)),
                        )),
                    ),
                )]));

                return (new Builder(...['api.json' => $document]))->prepareToSave();
            },
            'Operation "orphan" is not registered',
        ];

        // A dynamic anchor is found by name, and a schema written out in place has no
        // name: such a reference would lead nowhere, so the build says so instead.
        yield '$dynamicRef to a schema outside components' => [
            static function (): array {
                $anchored = new Schemas\Object\Schema(
                    resource: new Schemas\Abstract\Resource(dynamicAnchor: 'node'),
                );
                $document = new Openapi(
                    info: new Info(title: 'Dynamic', version: '1.0.0'),
                    components: new Components(schemas: new Schemas\Untyped\Schemas(
                        Tree: new Schemas\Untyped\Schema(
                            resource: new Schemas\Abstract\Resource(dynamicRef: $anchored),
                        ),
                    )),
                    version: Version::V311,
                );

                return (new Builder(...['api.json' => $document]))->prepareToSave();
            },
            'must be registered in components.schemas',
        ];

        yield '$schema without $id' => [
            // "MAY be present in any Schema Object that is a schema resource root",
            // and the root of a resource is a schema that has an $id
            static fn () => new Schemas\Abstract\Resource(schema: 'https://json-schema.org/draft/2020-12/schema'),
            'only allowed on a schema resource declaring "$id"',
        ];

        yield '$schema in 3.0' => [
            static function (): array {
                $document = new Openapi(
                    info: self::info(),
                    components: new Components(schemas: new Schemas\Untyped\Schemas(
                        Node: new Schemas\Object\Schema(resource: new Schemas\Abstract\Resource(
                            id: 'https://example.com/node',
                            schema: 'https://json-schema.org/draft/2020-12/schema',
                        )),
                    )),
                );

                return (new Builder(...['api.json' => $document]))->prepareToSave();
            },
            'only available in OpenAPI 3.1',
        ];

        yield 'null type in 3.0' => [
            static function (): array {
                $document = new Openapi(
                    info: self::info(),
                    components: new Components(
                        schemas: new Schemas\Untyped\Schemas(Nothing: new Schemas\Null\Schema()),
                    ),
                );

                return (new Builder(...['api.json' => $document]))->prepareToSave();
            },
            'only available in OpenAPI 3.1',
        ];

        yield 'nullable null schema' => [
            static fn () => new Schemas\Null\Schema(nullable: true),
            'already null',
        ];

        yield 'extension without a name' => [
            static fn () => Extensions::fromArray(['' => true]),
            'must not be empty',
        ];

        yield 'extensions on a plain map of responses' => [
            static fn () => new Components(
                responses: Responses::fromArray(
                    ['NotFound' => new Responses\Response(description: 'Missing.')],
                    new Extensions(internal: true),
                ),
            ),
            'components.responses is a plain map',
        ];

        yield 'extensions on webhooks' => [
            static fn () => new Openapi(
                info: self::info(),
                version: Version::V310,
                webhooks: PathItems::fromArray(
                    ['userCreated' => new Paths\Path(get: self::operation())],
                    new Extensions(internal: true),
                ),
            ),
            'webhooks is a plain map',
        ];

        yield 'discriminator extensions in 3.0' => [
            static function (): array {
                $dog = new Schemas\Object\Schema(properties: new Schemas\Object\Properties());
                $pet = new Schemas\Untyped\Schema(
                    oneOf: new Schemas\Untyped\Schemas($dog),
                    discriminator: new Schemas\Abstract\Discriminator(
                        propertyName: 'petType',
                        mapping: new Schemas\Untyped\Schemas(dog: $dog),
                        extensions: new Extensions(internal: true),
                    ),
                );
                $document = new Openapi(
                    info: self::info(),
                    components: new Components(schemas: new Schemas\Untyped\Schemas(Dog: $dog, Pet: $pet)),
                );

                return (new Builder(...['api.json' => $document]))->prepareToSave();
            },
            'only available in OpenAPI 3.1',
        ];

        yield 'basic scheme through the generic HTTP class' => [
            static fn () => new SecuritySchemes\HttpSecurityScheme('Basic'),
            'BasicHttpSecurityScheme',
        ];

        yield 'HTTP scheme that is not a token' => [
            static fn () => new SecuritySchemes\HttpSecurityScheme('Digest auth'),
            'RFC 7230 token',
        ];

        yield 'role names in 3.0' => [
            static function (): array {
                $bearer = new SecuritySchemes\BearerHttpSecurityScheme();
                $document = new Openapi(
                    info: self::info(),
                    components: new Components(securitySchemes: new SecuritySchemes(bearer: $bearer)),
                    security: new Securities(new Securities\SecuritySchemes(new Securities\Role($bearer, 'admin'))),
                );

                return (new Builder(...['api.json' => $document]))->prepareToSave();
            },
            'only available in OpenAPI 3.1',
        ];

        yield 'operation without responses in 3.0' => [
            static function (): array {
                $document = self::document(new Paths(...['/ping' => new Paths\Path(get: new Paths\Operation())]));

                return (new Builder(...['api.json' => $document]))->prepareToSave();
            },
            'must declare responses in OpenAPI 3.0.3',
        ];
    }

    /**
     * The `x-` prefix is always added, so every field of the document has exactly one
     * spelling, and `x-x-legacy`, which the specification allows, is written as
     * `x-legacy`.
     */
    public function testExtensionNamesAlwaysGainThePrefix(): void
    {
        $extensions = Extensions::fromArray([
            'internal' => true,
            'x-legacy' => 1,
            'oai-defined' => 'ok',
        ]);

        self::assertSame(
            ['type' => 'string', 'x-internal' => true, 'x-x-legacy' => 1, 'x-oai-defined' => 'ok'],
            $extensions->appendTo(['type' => 'string']),
        );
    }

    public function testIntegerLikeNamesSurviveThroughFromArray(): void
    {
        $properties = Schemas\Object\Properties::fromArray(['7' => self::property(), '-1' => self::property()]);
        $object = Schemas\Object\OpenapiObject::fromArray(['0' => 'a', 'b' => 1]);

        self::assertSame(['7', '-1'], array_map('strval', array_keys($properties->items)));
        self::assertSame(['0', 'b'], array_map('strval', array_keys($object->items)));
    }

    public function testRequiredNameMatchedByPatternIsAccepted(): void
    {
        $schema = new Schemas\Object\Schema(
            patternProperties: Schemas\Object\PatternProperties::fromArray(['^x-' => new Schemas\String\Schema()]),
            additionalProperties: false,
            required: new Schemas\String\Strings('x-id'),
        );

        self::assertSame(['x-id'], $schema->required->items);
    }

    public function testAcceptsValidDocument(): void
    {
        $document = self::document(new Paths(...[
            '/users/{id}' => new Paths\Path(get: self::operation(self::pathParameters())),
        ]));

        self::assertSame(Version::V303, $document->version);
    }

    private static function property(): Schemas\Object\Property
    {
        return new Schemas\Object\Property(new Schemas\String\Schema());
    }

    private static function info(): Info
    {
        return new Info(title: 'Validation', version: '1.0.0');
    }

    private static function document(Paths $paths, ?Components $components = null): Openapi
    {
        return new Openapi(info: self::info(), components: $components, paths: $paths);
    }

    private static function operation(?Parameters\Parameters $parameters = null): Paths\Operation
    {
        return new Paths\Operation(
            responses: new Responses(x200: new Responses\Response(description: 'OK')),
            parameters: $parameters,
        );
    }

    private static function pathParameters(): Parameters\Parameters
    {
        return new Parameters\Parameters(
            paths: new Parameters\Path\Paths(
                id: new Parameters\Path\SchemaParameter(schema: new Schemas\String\Schema()),
            ),
        );
    }
}
