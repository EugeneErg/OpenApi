<?php

declare(strict_types = 1);

namespace Tests;

use EugeneErg\OpenApi\Builder;
use EugeneErg\OpenApi\Openapi;
use EugeneErg\OpenApi\Reader;
use EugeneErg\OpenApi\Serialization\YamlDecoder;
use EugeneErg\OpenApi\Serialization\YamlEncoder;
use EugeneErg\OpenApi\Version;
use PHPUnit\Framework\TestCase;
use Tests\Support\RandomDocument;
use Throwable;

use function extension_loaded;
use function sprintf;

/**
 * The same properties as the hand-written cases check, on documents nobody wrote.
 *
 * Every document in tests/Cases exercises what somebody thought of. This test exercises
 * combinations nobody would think of, and it states the properties as laws rather than as
 * expected texts:
 *
 *  - a build is idempotent: reading what was built and building it again gives the same
 *    text, or something was lost, renamed or duplicated in between;
 *  - the reading is strict, so a keyword the package does not understand would be named
 *    here as well — and the package built the document itself, so there cannot be one;
 *  - the same document written in YAML reads back into the same objects: the YAML encoder
 *    is ours, and its mistakes are invisible in JSON;
 *  - the verbose form is the same specification: read back and built briefly, it has to
 *    match the brief form.
 *
 * A failure names the seed, and `RandomDocument` is reproducible, so the document can be
 * rebuilt and looked at.
 */
final class RoundTripPropertyTest extends TestCase
{
    /**
     * Enough documents to cross the features against each other, few enough to keep the
     * suite a second long.
     */
    private const int SEEDS = 120;

    /**
     * @dataProvider provideVersionCases
     */
    public function testBuildingIsIdempotent(Version $version): void
    {
        foreach (self::documents($version) as $seed => $openapi) {
            $first = self::encode($openapi, $seed);
            $second = self::encode(Reader::read($first), $seed, $first);

            self::assertSame($first, $second, sprintf('seed %d, %s', $seed, $version->value));
        }
    }

    /**
     * @dataProvider provideVersionCases
     */
    public function testYamlCarriesTheSameDocument(Version $version): void
    {
        if (!extension_loaded('yaml')) {
            self::markTestSkipped('ext-yaml is required to read YAML back.');
        }

        foreach (self::documents($version) as $seed => $openapi) {
            $json = self::encode($openapi, $seed);
            $yaml = self::only((new Builder(...['openapi.yaml' => $openapi]))->encode(encoder: new YamlEncoder()));

            $read = Reader::read($yaml, new YamlDecoder());

            self::assertSame(
                $json,
                self::encode($read, $seed, $yaml),
                sprintf('seed %d, %s', $seed, $version->value),
            );
        }
    }

    /**
     * @dataProvider provideVersionCases
     */
    public function testVerboseIsTheSameSpecification(Version $version): void
    {
        foreach (self::documents($version) as $seed => $openapi) {
            $brief = self::encode($openapi, $seed);
            $verbose = self::only((new Builder(...['openapi.json' => $openapi]))->encode(verbose: true));

            self::assertSame(
                $brief,
                self::encode(Reader::read($verbose), $seed, $verbose),
                sprintf('seed %d, %s', $seed, $version->value),
            );
        }
    }

    /**
     * @dataProvider provideVersionCases
     */
    public function testSeveralFilesKeepTheirReferences(Version $version): void
    {
        for ($seed = 1; $seed <= self::SEEDS; ++$seed) {
            // the pooled schemas are declared in one document and used in all of them, so
            // what is checked here is the cross-file `$ref`: it has to survive a round trip
            // pointing at the same file and the same name
            $documents = (new RandomDocument($seed, $version))->buildAll(2 + $seed % 2);
            $first = (new Builder(...$documents))->encode();
            $second = (new Builder(...Reader::readAll($first)))->encode();

            self::assertSame($first, $second, sprintf('seed %d, %s', $seed, $version->value));
        }
    }

    /**
     * @return iterable<string, array{Version}>
     */
    public static function provideVersionCases(): iterable
    {
        yield '3.0' => [Version::V303];

        yield '3.1' => [Version::V311];
    }

    /**
     * @return iterable<int, Openapi>
     */
    private static function documents(Version $version): iterable
    {
        for ($seed = 1; $seed <= self::SEEDS; ++$seed) {
            try {
                yield $seed => (new RandomDocument($seed, $version))->build();
            } catch (Throwable $exception) {
                // the generator builds through the same constructors, so a refusal here
                // is its own mistake and has to be told apart from a defect of the package
                self::fail(sprintf(
                    "The generator built an invalid document (seed %d, %s):\n%s",
                    $seed,
                    $version->value,
                    $exception->getMessage(),
                ));
            }
        }
    }

    /**
     * @param array<string, string> $encoded
     */
    private static function only(array $encoded): string
    {
        foreach ($encoded as $content) {
            return $content;
        }

        self::fail('The builder returned no document.');
    }

    /**
     * @param null|string $source the text the document was read from, when it was read
     *                            from one: without it a failure says what broke but not
     *                            on what
     */
    private static function encode(Openapi $openapi, int $seed, ?string $source = null): string
    {
        try {
            return self::only((new Builder(...['openapi.json' => $openapi]))->encode());
        } catch (Throwable $exception) {
            self::fail(sprintf(
                "%s failed (seed %d):\n%s%s",
                $source === null ? 'Building the generated document' : 'Rebuilding what was read',
                $seed,
                $exception->getMessage(),
                $source === null ? '' : "\n\nThe document read was:\n" . $source,
            ));
        }
    }
}
