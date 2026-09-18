<?php

declare(strict_types = 1);

namespace Tests;

use EugeneErg\OpenApi\Builder;
use EugeneErg\OpenApi\Exceptions\OpenapiExceptionInterface;
use EugeneErg\OpenApi\Openapi;
use EugeneErg\OpenApi\Reader;
use EugeneErg\OpenApi\Serialization\YamlDecoder;
use EugeneErg\OpenApi\Serialization\YamlEncoder;
use EugeneErg\OpenApi\Version;
use PHPUnit\Framework\TestCase;
use stdClass;
use Tests\Support\RandomDocument;
use Throwable;

use function count;
use function extension_loaded;
use function is_array;
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
     * One Builder may hold documents of different versions, as long as they do not refer
     * to each other: each is written to its own version, and reading them back gives the
     * same texts.
     */
    public function testDocumentsOfDifferentVersionsBuildTogether(): void
    {
        for ($seed = 1; $seed <= self::SEEDS; ++$seed) {
            $documents = RandomDocument::mixedVersions($seed, 2 + $seed % 2);
            $first = (new Builder(...$documents))->encode();
            $second = (new Builder(...Reader::readAll($first)))->encode();

            self::assertSame($first, $second, sprintf('seed %d', $seed));
        }
    }

    /**
     * A document is somebody else's text, and it may be broken in any way at all. Reading
     * it may succeed or be refused, and nothing else: a TypeError, a warning or a walk
     * that never ends means the reader met a shape it does not describe.
     *
     * The documents are the generated ones, mutated: values replaced by values of another
     * kind, keys dropped, subtrees wrapped, keys renamed to the ones the reader treats
     * specially. Most mutations are refused, which is the point — the refusal has to be
     * the package's own.
     */
    public function testAnyDocumentIsEitherReadOrRefused(): void
    {
        $mutations = 0;

        foreach ([Version::V303, Version::V311] as $version) {
            for ($seed = 1; $seed <= 40; ++$seed) {
                $json = self::encode((new RandomDocument($seed, $version))->build(), $seed);

                for ($round = 0; $round < 4; ++$round) {
                    $mutated = self::mutate($json, $seed * 100 + $round);
                    ++$mutations;

                    try {
                        Reader::read($mutated, strict: $round % 2 === 0);
                    } catch (OpenapiExceptionInterface) {
                        // a refusal is an answer
                    } catch (Throwable $exception) {
                        self::fail(sprintf(
                            "Reading a mutated document broke instead of refusing (seed %d, round %d):\n%s: %s\n\n%s",
                            $seed,
                            $round,
                            $exception::class,
                            $exception->getMessage(),
                            $mutated,
                        ));
                    }
                }
            }
        }

        self::assertSame(320, $mutations);
    }

    /**
     * One to three mutations of the decoded document, by a seed of their own.
     */
    private static function mutate(string $json, int $seed): string
    {
        $value = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        $state = $seed;
        $random = static function (int $max) use (&$state): int {
            $state = ($state * 1_103_515_245 + 12_345) & 0x7FFFFFFF;

            return $max <= 0 ? 0 : $state % ($max + 1);
        };

        for ($i = $random(2); $i >= 0; --$i) {
            $value = self::mutated($value, 0, $random);
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    /**
     * @param callable(int): int $random
     */
    private static function mutated(mixed $value, int $depth, callable $random): mixed
    {
        $keys = $value instanceof stdClass ? array_keys(get_object_vars($value)) : [];

        switch ($random(10)) {
            case 0:
                return 'text';

            case 1:
                return 42;

            case 2:
                return true;

            case 3:
                return null;

            case 4:
                return [];

            case 5:
                return new stdClass();

            case 6:
                return [$value];

            case 7:
                // a key the reader treats specially, in a place that does not expect it
                $names = ['$ref', '$dynamicRef', 'x-', '0', '', 'enum', 'type'];

                return (object) [$names[$random(count($names) - 1)] ?? '$ref' => $value];

            default:
                if ($keys !== [] && $depth < 6) {
                    $key = (string) ($keys[$random(count($keys) - 1)] ?? $keys[0]);

                    if ($random(4) === 0) {
                        unset($value->{$key});

                        return $value;
                    }

                    /** @var stdClass $value */
                    $value->{$key} = self::mutated($value->{$key}, $depth + 1, $random);

                    return $value;
                }

                if (is_array($value) && $value !== [] && $depth < 6) {
                    $index = array_keys($value)[$random(count($value) - 1)] ?? array_key_first($value);
                    $value[$index] = self::mutated($value[$index] ?? null, $depth + 1, $random);

                    return $value;
                }

                return $value;
        }
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
