<?php

declare(strict_types = 1);

namespace Tests;

use EugeneErg\OpenApi\Builder;
use EugeneErg\OpenApi\Reader;
use EugeneErg\OpenApi\Serialization\DecoderInterface;
use EugeneErg\OpenApi\Serialization\JsonDecoder;
use EugeneErg\OpenApi\Serialization\YamlDecoder;
use PHPUnit\Framework\TestCase;
use stdClass;
use Tests\Support\SemanticDiff;

use function basename;
use function glob;
use function implode;

/**
 * Настоящие спецификации переживают полный круг «чтение → запись» без потери смысла.
 *
 * Откуда файлы — tests/Cases/RealWorldTest/SOURCES.md. Что считается незначимым
 * различием — tests/Support/SemanticDiff.php.
 */
final class RealWorldTest extends TestCase
{
    /**
     * @dataProvider provideSpecificationKeepsItsMeaningCases
     */
    public function testSpecificationKeepsItsMeaning(string $file): void
    {
        $decoder = self::decoder($file);
        $content = (string) file_get_contents($file);

        $built = (new Builder(...['openapi.json' => Reader::read($content, $decoder)]))->prepareToSave();
        $original = $decoder->decode($content);
        $rebuilt = json_decode((string) json_encode($built['openapi.json'] ?? null));

        self::assertInstanceOf(stdClass::class, $original);
        self::assertInstanceOf(stdClass::class, $rebuilt);

        $diff = new SemanticDiff($original, $rebuilt);

        self::assertSame([], $diff->differences, implode("\n", $diff->differences));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideSpecificationKeepsItsMeaningCases(): iterable
    {
        foreach (glob(__DIR__ . '/Cases/RealWorldTest/*.{json,yaml}', GLOB_BRACE) ?: [] as $file) {
            yield basename($file) => [$file];
        }
    }

    private static function decoder(string $file): DecoderInterface
    {
        if (str_ends_with($file, '.json')) {
            return new JsonDecoder();
        }

        if (!YamlDecoder::isAvailable()) {
            self::markTestSkipped('ext-yaml is not installed.');
        }

        return new YamlDecoder();
    }
}
