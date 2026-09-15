<?php

declare(strict_types = 1);

namespace Tests;

use Closure;
use EugeneErg\OpenApi\Builder;
use EugeneErg\OpenApi\Components\Examples;
use EugeneErg\OpenApi\Components\Examples\Example;
use EugeneErg\OpenApi\Components\Parameters;
use EugeneErg\OpenApi\Components\RequestBodies;
use EugeneErg\OpenApi\Components\Responses;
use EugeneErg\OpenApi\Components\Schemas;
use EugeneErg\OpenApi\Exceptions\OpenapiExceptionInterface;
use EugeneErg\OpenApi\Info;
use EugeneErg\OpenApi\Openapi;
use EugeneErg\OpenApi\PathItems;
use EugeneErg\OpenApi\Paths;
use EugeneErg\OpenApi\Reference;
use EugeneErg\OpenApi\Version;
use PHPUnit\Framework\TestCase;

/**
 * Состояния, которые нельзя выразить типами, но которые обязаны быть недостижимы.
 *
 * Все они бросают исключения пакета, поэтому ловятся одним OpenapiExceptionInterface.
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
    }

    public function testAcceptsValidDocument(): void
    {
        $document = self::document(new Paths(...[
            '/users/{id}' => new Paths\Path(get: self::operation(self::pathParameters())),
        ]));

        self::assertSame(Version::V303, $document->version);
    }

    private static function info(): Info
    {
        return new Info(title: 'Validation', version: '1.0.0');
    }

    private static function document(Paths $paths): Openapi
    {
        return new Openapi(info: self::info(), paths: $paths);
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
