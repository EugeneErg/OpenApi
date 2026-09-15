<?php

declare(strict_types = 1);

namespace Tests;

use EugeneErg\OpenApi\Builder;
use EugeneErg\OpenApi\Openapi;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

use function is_array;
use function is_string;
use function sprintf;

final class BuilderTest extends TestCase
{
    /**
     * @dataProvider providePrepareToSaveSuccessCases
     *
     * @param array<string, Openapi> $openapi
     */
    public function testPrepareToSaveSuccess(array $openapi, string $expected): void
    {
        $results = (object) (new Builder(...$openapi))->prepareToSave();

        // Сравнение по значению, а не по идентичности: ожидаемый результат —
        // раскодированный JSON, порядок ключей в нём роли не играет.
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
