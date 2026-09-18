<?php

declare(strict_types = 1);

namespace Tests;

use EugeneErg\OpenApi\Builder;
use EugeneErg\OpenApi\Info;
use EugeneErg\OpenApi\Openapi;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

use function is_array;
use function is_string;
use function sprintf;

final class BuilderTest extends TestCase
{
    /**
     * `save()` is the only part of the package that touches the disk: it creates the
     * directories on the way and writes what `encode()` would have returned.
     */
    public function testSaveWritesTheDocumentsToDisk(): void
    {
        $directory = sys_get_temp_dir() . '/eugene-erg-openapi-save-' . bin2hex(random_bytes(6));
        $document = new Openapi(info: new Info(title: 'Saved', version: '1.0.0'));

        try {
            $written = (new Builder(...['api.json' => $document]))->save($directory . '/docs');

            self::assertSame([$directory . '/docs/api.json'], array_keys($written));
            self::assertFileExists($directory . '/docs/api.json');
            self::assertSame(
                $written[$directory . '/docs/api.json'] ?? '',
                file_get_contents($directory . '/docs/api.json'),
            );
        } finally {
            // the directory was made here, so it is taken away here
            @unlink($directory . '/docs/api.json');
            @rmdir($directory . '/docs');
            @rmdir($directory);
        }
    }

    /**
     * @dataProvider providePrepareToSaveSuccessCases
     *
     * @param array<string, Openapi> $openapi
     */
    public function testPrepareToSaveSuccess(array $openapi, string $expected): void
    {
        $results = (object) (new Builder(...$openapi))->prepareToSave();

        // Compared by value rather than by identity: the expected result is decoded
        // JSON, and the order of the keys in it does not matter.
        self::assertEquals(json_decode($expected), $results);
    }

    /**
     * @return iterable<string, array{array<string, Openapi>, string}>
     */
    public static function providePrepareToSaveSuccessCases(): iterable
    {
        foreach (glob(__DIR__ . '/Cases/BuilderTest/Objects/*.php') ?: [] as $objectPath) {
            $name = basename($objectPath, '.php');
            $jsonPath = __DIR__ . '/Cases/BuilderTest/Jsons/' . $name . '.json';

            self::assertFileExists($jsonPath);

            yield $name => [self::loadDocuments($objectPath), (string) file_get_contents($jsonPath)];
        }
    }

    /**
     * @return array<string, Openapi>
     */
    private static function loadDocuments(string $path): array
    {
        $documents = require $path;

        if (!is_array($documents)) {
            throw new UnexpectedValueException(sprintf('Case "%s" must return an array.', $path));
        }

        $result = [];

        foreach ($documents as $fileName => $document) {
            if (!is_string($fileName) || !$document instanceof Openapi) {
                throw new UnexpectedValueException(sprintf(
                    'Case "%s" must return a map of file name to Openapi.',
                    $path,
                ));
            }

            $result[$fileName] = $document;
        }

        return $result;
    }
}
