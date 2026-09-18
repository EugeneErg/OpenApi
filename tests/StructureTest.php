<?php

declare(strict_types = 1);

namespace Tests;

use EugeneErg\OpenApi\Exceptions\InvalidDocumentOpenapiException;
use EugeneErg\OpenApi\Serialization\Structure;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * `Structure` is what a decoder of one's own is written with: README shows a
 * `DecoderInterface` over symfony/yaml that is one call to `toObject()`.
 *
 * The rule it carries is the one a hand-written decoder gets wrong: Reader works on the
 * shape json_decode gives — maps as stdClass, lists as arrays — and a parser that returns
 * associative arrays for both loses the difference between `{}` and `[]`.
 */
final class StructureTest extends TestCase
{
    public function testMapsBecomeObjectsAndListsStayArrays(): void
    {
        $result = Structure::toObject([
            'info' => ['title' => 'x', 'tags' => ['a', 'b']],
            'servers' => [['url' => 'https://example.com']],
        ]);

        self::assertInstanceOf(stdClass::class, $result);
        self::assertInstanceOf(stdClass::class, $result->info);
        self::assertSame(['a', 'b'], $result->info->tags);
        $servers = $result->servers;

        self::assertIsArray($servers);

        $server = $servers[0] ?? null;

        self::assertInstanceOf(stdClass::class, $server);
        self::assertSame('https://example.com', $server->url);
    }

    public function testScalarsAndNullPassThrough(): void
    {
        // normalize() takes any JSON value; toObject() is the entry point for a whole
        // document, and there the top level has to be a mapping
        self::assertSame('text', Structure::normalize('text'));
        self::assertSame(5, Structure::normalize(5));
        self::assertSame(1.5, Structure::normalize(1.5));
        self::assertTrue(Structure::normalize(true));
        self::assertNull(Structure::normalize(null));
    }

    public function testADocumentThatIsNotAMappingIsRejected(): void
    {
        $this->expectException(InvalidDocumentOpenapiException::class);
        $this->expectExceptionMessage('must be a mapping at the top level');

        Structure::toObject('openapi: 3.1.1');
    }

    /**
     * An empty structure is the one thing that cannot be told apart: `{}` and `[]` both
     * arrive as an empty array, so it stays a list and Reader accepts either form where
     * it expects a map.
     */
    public function testAnEmptyStructureStaysAList(): void
    {
        self::assertSame([], Structure::normalize([]));
    }

    /**
     * ext-yaml resolves an anchor that points at the node carrying it into a structure
     * with no end: `$parsed['a']['b'] === $parsed['a']`. Walking that never finishes, so
     * the depth is bounded — at the same 512 levels json_decode() stops at.
     */
    public function testAStructureWithoutAnEndIsRefused(): void
    {
        $this->expectException(InvalidDocumentOpenapiException::class);
        $this->expectExceptionMessage('nests deeper than 512 levels');

        $value = ['b' => null];
        $value['b'] = &$value;

        Structure::normalize(['a' => $value]);
    }

    /**
     * A numeric property name comes back from PHP as an int, and the keys are normalised
     * so that a response code stays the string it was.
     */
    public function testNumericKeysComeBackAsStrings(): void
    {
        $value = new stdClass();
        $value->{'200'} = 'OK';

        self::assertSame(['200' => 'OK'], Structure::vars($value));
    }
}
