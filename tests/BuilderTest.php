<?php

declare(strict_types = 1);

namespace Tests;

use EugeneErg\OpenApi\Builder;
use EugeneErg\OpenApi\Openapi;
use Generator;
use PHPUnit\Framework\TestCase;

final class BuilderTest extends TestCase
{
    private const CASES = [];

    /**
     * @dataProvider getPrepareToSaveSuccessData
     *
     * @param array<string, Openapi> $openapi
     */
    public function testPrepareToSaveSuccess(array $openapi, string $expected): void
    {
        $results = (new Builder(...$openapi))->prepareToSave();

        self::assertEquals(json_decode($expected), $results);
    }

    public static function getPrepareToSaveSuccessData(): Generator
    {
        foreach (self::CASES as $objectPath => $jsonPath) {
            yield $objectPath => [require $objectPath, file_get_contents($jsonPath)];
        }
    }
}