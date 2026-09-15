<?php

declare(strict_types = 1);

namespace Tests;

use EugeneErg\OpenApi\Serialization\YamlEncoder;
use PHPUnit\Framework\TestCase;

/**
 * Кавычки ставятся не по вкусу, а по необходимости: лишние допустимы,
 * потерянный тип — нет. Поэтому правила зафиксированы построчно.
 */
final class YamlEncoderTest extends TestCase
{
    /**
     * @dataProvider provideEncodesScalarCases
     */
    public function testEncodesScalar(mixed $value, string $expected): void
    {
        self::assertSame("key: {$expected}\n", (new YamlEncoder())->encode((object) ['key' => $value]));
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function provideEncodesScalarCases(): iterable
    {
        yield 'plain word' => ['simple', 'simple'];

        yield 'sentence' => ['A list of pets.', 'A list of pets.'];

        yield 'url' => ['https://example.com/a', 'https://example.com/a'];

        yield 'non latin stays plain' => ['Идентификатор', 'Идентификатор'];

        yield 'numeric string is quoted' => ['200', '"200"'];

        yield 'version is not a number' => ['3.0.3', '3.0.3'];

        yield 'boolean-like string is quoted' => ['true', '"true"'];

        yield 'yaml 1.1 no is quoted' => ['no', '"no"'];

        yield 'empty string is quoted' => ['', '""'];

        yield 'colon with space is quoted' => ['a: b', '"a: b"'];

        yield 'colon without space stays plain' => ['read:pets', 'read:pets'];

        yield 'hash comment is quoted' => ['x # y', '"x # y"'];

        yield 'leading indicator is quoted' => ['*wildcard', '"*wildcard"'];

        yield 'runtime expression is quoted' => ['{$request.body#/url}', '"{$request.body#/url}"'];

        yield 'surrounding spaces are quoted' => ['  x  ', '"  x  "'];

        yield 'newline is escaped' => ["a\nb", '"a\nb"'];

        yield 'double quote is escaped' => ['say "hi"', '"say \"hi\""'];

        yield 'backslash is escaped' => ['a\b', '"a\\\b"'];

        yield 'integer' => [42, '42'];

        yield 'float keeps fraction' => [5.0, '5.0'];

        yield 'float' => [0.5, '0.5'];

        yield 'true' => [true, 'true'];

        yield 'false' => [false, 'false'];

        yield 'null' => [null, 'null'];
    }

    public function testEncodesEmptyCollections(): void
    {
        $document = (object) ['map' => (object) [], 'list' => []];

        self::assertSame("map: {}\nlist: []\n", (new YamlEncoder())->encode($document));
    }

    public function testEncodesNestedStructures(): void
    {
        $document = (object) [
            'paths' => (object) [
                '/users/{id}' => (object) [
                    'get' => (object) [
                        'parameters' => [
                            (object) ['name' => 'id', 'in' => 'path'],
                            (object) ['name' => 'q', 'in' => 'query'],
                        ],
                        'tags' => ['users'],
                    ],
                ],
            ],
        ];

        $expected = <<<'YAML'
            paths:
              /users/{id}:
                get:
                  parameters:
                    - name: id
                      in: path
                    - name: q
                      in: query
                  tags:
                    - users

            YAML;

        self::assertSame($expected, (new YamlEncoder())->encode($document));
    }
}
