<?php

declare(strict_types = 1);

namespace Tests;

use EugeneErg\OpenApi\Components\Schemas\Abstract\Xml;
use Generator;
use PHPUnit\Framework\TestCase;

final class XmlTest extends TestCase
{
    private const CASES = [
        __DIR__ . '/Cases/XmlTest/Objects/all-fields.php' => __DIR__ . '/Cases/XmlTest/Jsons/all-fields.json'
    ];

    public function testToObject(): void
    {
        new Xml('', '', '', false, false);
    }

    public static function getToObjectData(): Generator
    {
        foreach (self::CASES as $objectPath => $jsonPath) {
            yield $objectPath => [require $objectPath, file_get_contents($jsonPath)];
        }
    }
}