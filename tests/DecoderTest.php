<?php

declare(strict_types = 1);

namespace Tests;

use EugeneErg\OpenApi\Builder;
use EugeneErg\OpenApi\Exceptions\InvalidDocumentOpenapiException;
use EugeneErg\OpenApi\Openapi;
use EugeneErg\OpenApi\Reader;
use EugeneErg\OpenApi\Serialization\JsonDecoder;
use EugeneErg\OpenApi\Serialization\JsonEncoder;
use EugeneErg\OpenApi\Serialization\YamlDecoder;
use EugeneErg\OpenApi\Serialization\YamlEncoder;
use PHPUnit\Framework\TestCase;
use stdClass;
use UnexpectedValueException;

use function extension_loaded;
use function is_array;
use function is_string;
use function sprintf;

final class DecoderTest extends TestCase
{
    /**
     * @dataProvider provideDocumentCases
     *
     * @param array<string, Openapi> $documents
     */
    public function testJsonRoundTripIsExact(array $documents): void
    {
        foreach ((new Builder(...$documents))->prepareToSave() as $document) {
            $encoded = (new JsonEncoder())->encode($document);

            self::assertEquals($document, (new JsonDecoder())->decode($encoded));
        }
    }

    /**
     * Through ext-yaml an empty map and an empty list are indistinguishable: both `{}`
     * and `[]` arrive as an empty array. Reader restores the shape, because it knows what
     * is expected in every position, so the comparison here is up to that shape.
     *
     * @dataProvider provideDocumentCases
     *
     * @param array<string, Openapi> $documents
     */
    public function testYamlRoundTripPreservesEverythingButEmptyShape(array $documents): void
    {
        if (!YamlDecoder::isAvailable()) {
            self::markTestSkipped('ext-yaml is not installed.');
        }

        foreach ((new Builder(...$documents))->prepareToSave() as $document) {
            $encoded = (new YamlEncoder())->encode($document);

            self::assertSame(
                self::withoutEmptyShape($document),
                self::withoutEmptyShape((new YamlDecoder())->decode($encoded)),
            );
        }
    }

    /**
     * @return iterable<string, array{array<string, Openapi>}>
     */
    public static function provideDocumentCases(): iterable
    {
        foreach (glob(__DIR__ . '/Cases/BuilderTest/Objects/*.php') ?: [] as $objectPath) {
            $documents = require $objectPath;

            if (!is_array($documents)) {
                throw new UnexpectedValueException(sprintf('Case "%s" must return an array.', $objectPath));
            }

            $result = [];

            foreach ($documents as $fileName => $document) {
                if (!is_string($fileName) || !$document instanceof Openapi) {
                    throw new UnexpectedValueException(sprintf('Case "%s" returned an unexpected value.', $objectPath));
                }

                $result[$fileName] = $document;
            }

            yield basename($objectPath, '.php') => [$result];
        }
    }

    public function testRejectsBrokenJson(): void
    {
        $this->expectException(InvalidDocumentOpenapiException::class);
        $this->expectExceptionMessage('not valid JSON');

        (new JsonDecoder())->decode('{"openapi": ');
    }

    public function testRejectsNonObjectDocument(): void
    {
        $this->expectException(InvalidDocumentOpenapiException::class);
        $this->expectExceptionMessage('must be an object at the top level');

        (new JsonDecoder())->decode('[1, 2, 3]');
    }

    /**
     * `example: {}` on an object is an empty map, and through ext-yaml it arrives as an
     * empty array. The schema knows the position, so a map stays a map.
     *
     * Found on the Camunda 8 document.
     */
    public function testEmptyMappingStaysAMappingWhenReadFromYaml(): void
    {
        if (!YamlDecoder::isAvailable()) {
            self::markTestSkipped('ext-yaml is not installed.');
        }

        $document = Reader::read(<<<'YAML'
            openapi: 3.0.3
            info:
              title: Links
              version: '1.0.0'
            paths: {}
            components:
              schemas:
                Links:
                  type: object
                  additionalProperties:
                    type: string
                  example: {}
            YAML, new YamlDecoder());

        $built = (new Builder(...['openapi.yaml' => $document]))->prepareToSave();

        self::assertSame(
            '{"schemas":{"Links":{"type":"object","example":{},"additionalProperties":{"type":"string"}}}}',
            json_encode($built['openapi.yaml']->components ?? null),
        );
    }

    /**
     * A YAML anchor may point at the node that carries it, and then there is no document
     * to read: the structure has no end, and neither JSON nor the objects of this package
     * can express a cycle in data. Reading it used to hang.
     */
    public function testRecursiveAnchorIsRefused(): void
    {
        if (!extension_loaded('yaml')) {
            self::markTestSkipped('ext-yaml is required to read YAML.');
        }

        $this->expectException(InvalidDocumentOpenapiException::class);
        $this->expectExceptionMessage('without an end');

        Reader::read("openapi: 3.1.1\ninfo: &info\n  nested: *info\npaths: {}\n", new YamlDecoder());
    }

    /**
     * OpenAPI requires YAML 1.2: the YAML 1.1 rules ext-yaml uses by default quietly turn
     * strings into numbers and booleans.
     */
    public function testYamlIsReadByCoreSchema(): void
    {
        if (!YamlDecoder::isAvailable()) {
            self::markTestSkipped('ext-yaml is not installed.');
        }

        $decoded = (new YamlDecoder())->decode(<<<'YAML'
            y: yes
            n: 1
            country: NO
            switch: on
            ids: 521621,621373
            time: 12:30
            padded: 012
            hex: 0x1F
            flag: true
            nothing: ~
            date: 2024-01-02
            ratio: -.5E-3
            YAML);

        self::assertSame([
            'y' => 'yes',
            'n' => 1,
            'country' => 'NO',
            'switch' => 'on',
            'ids' => '521621,621373',
            'time' => '12:30',
            'padded' => 12,
            'hex' => 31,
            'flag' => true,
            'nothing' => null,
            'date' => '2024-01-02',
            'ratio' => -0.0005,
        ], get_object_vars($decoded));
    }

    private static function withoutEmptyShape(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            $value = get_object_vars($value);
        }

        if (!is_array($value)) {
            return $value;
        }

        return $value === [] ? '<empty>' : array_map(self::withoutEmptyShape(...), $value);
    }
}
