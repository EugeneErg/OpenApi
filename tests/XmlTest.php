<?php

declare(strict_types = 1);

namespace Tests;

use EugeneErg\OpenApi\Components\Schemas\Abstract\Xml;
use Generator;
use PHPUnit\Framework\TestCase;

final class XmlTest extends TestCase
{
    private const CASES = [
        __DIR__ . '/Cases/XmlTest/Objects/all-fields.php' => __DIR__ . '/Cases/XmlTest/Jsons/all-fields.json',
        __DIR__ . '/Cases/XmlTest/Objects/min-fields.php' => __DIR__ . '/Cases/XmlTest/Jsons/min-fields.json',
    ];

    /**
     * @dataProvider getToObjectData
     */
    public function testToObject(Xml $xml, string $expected): void
    {
        $results = $xml->toObject();

        self::assertEquals(json_decode($expected), $results);
    }

    public static function getToObjectData(): Generator
    {
        foreach (self::CASES as $objectPath => $jsonPath) {
            yield $objectPath => [require $objectPath, file_get_contents($jsonPath)];
        }
    }
}