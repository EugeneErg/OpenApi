<?php

declare(strict_types = 1);

/*
 * A check of the output by a third-party validator.
 *
 *     composer validate-output
 *
 * The read → write round trip proves the document was not lost, but not that it is
 * valid: the package could read and write in the same wrong way. So everything the
 * tests/Cases/BuilderTest cases build is handed to Redocly — in both formats, because
 * YAML is written by an encoder of our own and its mistakes are invisible in JSON.
 *
 * Only the structural rules (`struct`) are checked: stylistic advice such as "add a
 * licence" has nothing to do with validity.
 *
 * KNOWN_DEFECTS are the places where the validator, not the package, departs from the
 * specification. Every one of them was checked against the text of OpenAPI and left with
 * an explanation, because a check that fails on somebody else's mistakes is worse than no
 * check at all.
 *
 * Node.js is required; Redocly itself is downloaded through npx on the first run.
 */

use EugeneErg\OpenApi\Builder;
use EugeneErg\OpenApi\Openapi;
use EugeneErg\OpenApi\Serialization\YamlEncoder;

require __DIR__ . '/../vendor/autoload.php';

$directory = sys_get_temp_dir() . '/eugene-erg-openapi-validate';

if (!is_dir($directory) && !mkdir($directory, 0o775, true) && !is_dir($directory)) {
    fwrite(STDERR, "Cannot create {$directory}\n");

    exit(2);
}

foreach ((array) glob($directory . '/*') as $stale) {
    @unlink((string) $stale);
}

$config = $directory . '/struct-only.yaml';

file_put_contents($config, "rules:\n  struct: error\n");

$written = 0;

foreach ((array) glob(__DIR__ . '/../tests/Cases/BuilderTest/Objects/*.php') as $path) {
    $path = (string) $path;
    $case = basename($path, '.php');
    $documents = require $path;

    if (!is_array($documents)) {
        continue;
    }

    /** @var array<string, Openapi> $documents */
    $builder = new Builder(...$documents);

    // both formats: YAML is written by an encoder of our own, and JSON will not show its
    // mistakes; and both forms: the verbose one writes out the defaults, and getting
    // those wrong is just as easy
    foreach ([false, true] as $verbose) {
        $suffix = $verbose ? '--verbose' : '';

        foreach ($builder->encode('', null, $verbose) as $name => $content) {
            file_put_contents(sprintf('%s/%s--%s%s.json', $directory, $case, basename($name, '.json'), $suffix), $content);
            ++$written;
        }

        foreach ($builder->encode('', new YamlEncoder(), $verbose) as $name => $content) {
            file_put_contents(sprintf('%s/%s--%s%s.yaml', $directory, $case, basename($name, '.json'), $suffix), $content);
            ++$written;
        }
    }
}

printf("Built %d documents in %s\n", $written, $directory);

$command = sprintf(
    'cd %s && npx --yes @redocly/cli@2 lint --config %s *--*.json *--*.yaml 2>&1',
    escapeshellarg($directory),
    escapeshellarg(basename($config)),
);

/**
 * Where the validator departs from the specification: a substring of the message => why
 * this is not our mistake.
 */
const KNOWN_DEFECTS = [
    // JSON Schema 2020-12 declares $vocabulary an object of URI => bool; Redocly expects a string
    'at #/components/schemas/Node/$vocabulary' => '$vocabulary is an object in JSON Schema 2020-12',
];

exec($command, $output, $status);

$report = implode("\n", $output);

if ($status !== 0) {
    $unexplained = [];

    foreach ($output as $line) {
        if (!str_starts_with($line, '[')) {
            continue;
        }

        foreach (array_keys(KNOWN_DEFECTS) as $known) {
            if (str_contains($line, $known)) {
                continue 2;
            }
        }

        $unexplained[] = $line;
    }

    if ($unexplained === []) {
        foreach (KNOWN_DEFECTS as $known => $why) {
            printf("known validator defect: %s (%s)\n", $known, $why);
        }

        echo "Every document is valid.\n";

        exit(0);
    }

    echo $report, "\n";

    if (str_contains($report, 'npx: not found') || str_contains($report, 'command not found')) {
        fwrite(STDERR, "\nRedocly needs Node.js; install it or run the linter yourself on the files above.\n");
    }

    exit(1);
}

// in its report Redocly lists every file — only the total is kept
foreach ($output as $line) {
    if (str_contains($line, 'valid') || str_contains($line, 'error')) {
        echo $line, "\n";
    }
}
