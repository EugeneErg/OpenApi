<?php

declare(strict_types = 1);

namespace Tests;

use EugeneErg\OpenApi\Components\Schemas\Abstract\Xml;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

use function sprintf;

final class XmlTest extends TestCase
{
    /**
     * @dataProvider provideToObjectCases
     */
    public function testToObject(Xml $xml, string $expected): void
    {
        self::assertEquals(json_decode($expected), $xml->toObject());
    }

    /**
     * @return iterable<string, array{Xml, string}>
     */
    public static function provideToObjectCases(): iterable
    {
        foreach (glob(__DIR__ . '/Cases/XmlTest/Objects/*.php') ?: [] as $objectPath) {
            $name = basename($objectPath, '.php');
            $jsonPath = __DIR__ . '/Cases/XmlTest/Jsons/' . $name . '.json';

            self::assertFileExists($jsonPath);

            yield $name => [self::loadXml($objectPath), (string) file_get_contents($jsonPath)];
        }
    }

    private static function loadXml(string $path): Xml
    {
        $xml = require $path;

        if (!$xml instanceof Xml) {
            throw new UnexpectedValueException(sprintf('Case "%s" must return an Xml instance.', $path));
        }

        return $xml;
    }
}
