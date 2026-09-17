<?php

declare(strict_types = 1);

namespace Tests;

use Closure;
use EugeneErg\OpenApi\Builder;
use EugeneErg\OpenApi\Components;
use EugeneErg\OpenApi\Components\RequestBodies;
use EugeneErg\OpenApi\Components\Responses;
use EugeneErg\OpenApi\Components\Schemas;
use EugeneErg\OpenApi\Exceptions\OpenapiExceptionInterface;
use EugeneErg\OpenApi\Info;
use EugeneErg\OpenApi\Openapi;
use EugeneErg\OpenApi\Paths;
use EugeneErg\OpenApi\Version;
use PHPUnit\Framework\TestCase;

/**
 * A failure during a build has to name the place in the document.
 *
 * What refuses is the deepest object: it knows what it is missing but not where it lies —
 * and the call stack shows the insides of the package. So the place is gathered by the
 * containers on the way up.
 */
final class PlaceTest extends TestCase
{
    /**
     * @dataProvider provideFailureNamesItsPlaceCases
     *
     * @param Closure(): Openapi $document
     */
    public function testFailureNamesItsPlace(Closure $document, string $place): void
    {
        try {
            (new Builder(...['openapi.json' => $document()]))->prepareToSave();
            self::fail('The document was expected to be rejected.');
        } catch (OpenapiExceptionInterface $exception) {
            self::assertSame($place, $exception->place());
            self::assertStringStartsWith($place . ': ', $exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{Closure(): Openapi, string}>
     */
    public static function provideFailureNamesItsPlaceCases(): iterable
    {
        yield 'a component schema' => [
            static fn (): Openapi => new Openapi(
                info: self::info(),
                components: new Components(schemas: new Schemas\Untyped\Schemas(Tags: self::broken())),
            ),
            'openapi.json/components/schemas/Tags',
        ];

        yield 'a property of a component schema' => [
            static fn (): Openapi => new Openapi(
                info: self::info(),
                components: new Components(schemas: new Schemas\Untyped\Schemas(
                    User: new Schemas\Object\Schema(properties: new Schemas\Object\Properties(
                        tags: new Schemas\Object\Property(schema: self::broken()),
                    )),
                )),
            ),
            'openapi.json/components/schemas/User/properties/tags',
        ];

        yield 'a member of a composition' => [
            static fn (): Openapi => new Openapi(
                info: self::info(),
                components: new Components(schemas: new Schemas\Untyped\Schemas(
                    Either: new Schemas\Untyped\Schema(oneOf: new Schemas\Untyped\Schemas(
                        new Schemas\String\Schema(),
                        self::broken(),
                    )),
                )),
            ),
            'openapi.json/components/schemas/Either/oneOf/1',
        ];

        // a path template inside a pointer, as in the reader, by RFC 6901
        yield 'a media type of a response' => [
            static fn (): Openapi => new Openapi(
                info: self::info(),
                paths: Paths::fromArray(['/users/{id}' => new Paths\Path(
                    get: new Paths\Operation(
                        responses: Responses::fromArray(['200' => new Responses\Response(
                            description: 'OK',
                            content: RequestBodies\Contents::fromArray([
                                'application/json' => new RequestBodies\Content(schema: self::broken()),
                            ]),
                        )]),
                        parameters: new Components\Parameters\Parameters(
                            paths: new Components\Parameters\Path\Paths(
                                id: new Components\Parameters\Path\SchemaParameter(schema: new Schemas\String\Schema()),
                            ),
                        ),
                    ),
                )]),
            ),
            'openapi.json/paths/~1users~1{id}/get/responses/200/content/application~1json/schema',
        ];

        yield 'a word that 3.0 does not have' => [
            static fn (): Openapi => new Openapi(
                info: self::info(),
                components: new Components(schemas: new Schemas\Untyped\Schemas(
                    Node: new Schemas\Object\Schema(resource: new Schemas\Abstract\Resource(
                        defs: new Schemas\Untyped\Schemas(Leaf: new Schemas\String\Schema()),
                    )),
                )),
                version: Version::V303,
            ),
            'openapi.json/components/schemas/Node',
        ];
    }

    /**
     * An object that refused inside its constructor does not lie in any document yet:
     * there the call stack shows the place, because it is a line of code.
     */
    public function testPlaceIsUnknownBeforeTheDocumentExists(): void
    {
        try {
            new Schemas\String\Schema(minLength: 10, maxLength: 3);
            self::fail('The schema was expected to be rejected.');
        } catch (OpenapiExceptionInterface $exception) {
            self::assertNull($exception->place());
        }
    }

    /**
     * The file name is the first step: with several documents there is no telling which
     * of them is at fault without it.
     */
    public function testPlaceStartsWithTheFileName(): void
    {
        $documents = [
            'first.json' => new Openapi(info: self::info()),
            'second.json' => new Openapi(
                info: self::info(),
                components: new Components(schemas: new Schemas\Untyped\Schemas(Tags: self::broken())),
            ),
        ];

        try {
            (new Builder(...$documents))->prepareToSave();
            self::fail('The document was expected to be rejected.');
        } catch (OpenapiExceptionInterface $exception) {
            self::assertSame('second.json/components/schemas/Tags', $exception->place());
        }
    }

    /**
     * An array without `items`: in 3.0 that is a build error rather than a constructor's,
     * so only whoever knows where the schema lies can name the place.
     */
    private static function broken(): Schemas\Array\Schema
    {
        return new Schemas\Array\Schema();
    }

    private static function info(): Info
    {
        return new Info(title: 'Place', version: '1.0.0');
    }
}
