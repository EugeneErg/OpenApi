<?php

declare(strict_types = 1);

namespace Tests;

use EugeneErg\OpenApi\Builder;
use EugeneErg\OpenApi\Openapi;
use EugeneErg\OpenApi\Reader;
use EugeneErg\OpenApi\Serialization\JsonEncoder;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

use function count;
use function is_array;
use function is_string;
use function sprintf;

/**
 * The verbose form is the same specification, written out in full.
 *
 * That is exactly what is checked here: reading a verbose document and building it
 * briefly gives the same thing as building it briefly from the start. So not a single
 * written-out value changed the meaning — otherwise "verbose" would be another document.
 *
 * It checks both sides at once: strict reading would stumble on a word the package does
 * not understand, and a superfluous value that changes the meaning would diverge from the
 * brief form.
 */
final class VerboseTest extends TestCase
{
    /**
     * @dataProvider provideVerboseDocumentMeansTheSameCases
     */
    public function testVerboseDocumentMeansTheSame(string $brief, string $verbose): void
    {
        self::assertNotSame($brief, $verbose, 'Verbose output is not supposed to equal the brief one.');
        self::assertSame($brief, self::brief(Reader::read($verbose)));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideVerboseDocumentMeansTheSameCases(): iterable
    {
        foreach (glob(__DIR__ . '/Cases/BuilderTest/Objects/*.php') ?: [] as $objectPath) {
            $documents = self::load((string) $objectPath);

            // the multi-file cases are read together only: a single read knows nothing
            // of the neighbours and will not resolve the references between the files
            if (count($documents) !== 1) {
                continue;
            }

            foreach ($documents as $document) {
                $brief = self::brief($document);
                $verbose = (new JsonEncoder())->encode(
                    (new Builder(...['openapi.json' => $document]))->prepareToSave(verbose: true)['openapi.json']
                    ?? throw new UnexpectedValueException('Builder returned nothing.'),
                );

                // a case without a single default value has nothing to be compared with
                if ($brief === $verbose) {
                    continue;
                }

                yield basename((string) $objectPath, '.php') => [$brief, $verbose];
            }
        }
    }

    private static function brief(Openapi $document): string
    {
        $result = (new Builder(...['openapi.json' => $document]))->prepareToSave();

        return (new JsonEncoder())->encode(
            $result['openapi.json'] ?? throw new UnexpectedValueException('Builder returned nothing.'),
        );
    }

    /**
     * @return array<string, Openapi>
     */
    private static function load(string $path): array
    {
        $documents = require $path;

        if (!is_array($documents)) {
            throw new UnexpectedValueException(sprintf('Case "%s" must return an array.', $path));
        }

        $result = [];

        foreach ($documents as $fileName => $document) {
            if (!is_string($fileName) || !$document instanceof Openapi) {
                throw new UnexpectedValueException(sprintf('Case "%s" returned an unexpected value.', $path));
            }

            $result[$fileName] = $document;
        }

        return $result;
    }
}
