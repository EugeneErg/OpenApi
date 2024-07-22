<?php

declare(strict_types = 1);

namespace Tests;

use EugeneErg\OpenApi\Builder;
use EugeneErg\OpenApi\Openapi;
use Generator;
use PHPUnit\Framework\TestCase;

final class BuilderTest extends TestCase
{
    private const array CASES = [
        __DIR__ . '/Cases/BuilderTest/Objects/components-in-outside.php' => __DIR__ . '/Cases/BuilderTest/Jsons/components-in-outside.json',
    ];

    /**
     * @dataProvider getPrepareToSaveSuccessData
     *
     * @param array<string, Openapi> $openapi
     */
    public function testPrepareToSaveSuccess(array $openapi, string $expected): void
    {


        $results = (object) (new Builder(...$openapi))->prepareToSave();
        $expected = json_decode($expected);

        self::assertEquals($expected, $results);
    }

    public static function getPrepareToSaveSuccessData(): Generator
    {
        foreach (self::CASES as $objectPath => $jsonPath) {
            yield $objectPath => [require $objectPath, file_get_contents($jsonPath)];
        }
    }
}