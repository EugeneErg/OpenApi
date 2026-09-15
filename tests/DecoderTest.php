<?php

declare(strict_types = 1);

namespace Tests;

use EugeneErg\OpenApi\Builder;
use EugeneErg\OpenApi\Exceptions\InvalidDocumentOpenapiException;
use EugeneErg\OpenApi\Openapi;
use EugeneErg\OpenApi\Serialization\JsonDecoder;
use EugeneErg\OpenApi\Serialization\JsonEncoder;
use EugeneErg\OpenApi\Serialization\YamlDecoder;
use EugeneErg\OpenApi\Serialization\YamlEncoder;
use PHPUnit\Framework\TestCase;
use stdClass;
use UnexpectedValueException;

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
     * Через ext-yaml пустая карта и пустой список неразличимы: и `{}`, и `[]`
     * приходят пустым массивом. Форму восстанавливает Reader, который знает,
     * что ожидается в каждой позиции, поэтому здесь сравнение с точностью до неё.
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
