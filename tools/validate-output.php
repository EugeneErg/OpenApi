<?php

declare(strict_types = 1);

/*
 * Проверка вывода сторонним валидатором.
 *
 *     composer validate-output
 *
 * Круг «чтение → запись» доказывает, что документ не потерялся, но не то, что он
 * валиден: пакет мог бы одинаково неверно и читать, и писать. Поэтому всё, что
 * собирают кейсы tests/Cases/BuilderTest, отдаётся Redocly — в двух форматах,
 * потому что YAML пишет свой энкодер и его ошибки в JSON не видны.
 *
 * Проверяются только структурные правила (`struct`): стилевые советы вроде
 * «добавьте лицензию» к валидности не относятся.
 *
 * KNOWN_DEFECTS — места, где валидатор расходится со спецификацией, а не пакет.
 * Каждое проверено по тексту OpenAPI и оставлено с объяснением, потому что
 * проверка, падающая на чужих ошибках, хуже отсутствующей.
 *
 * Нужен Node.js; сам Redocly скачивается через npx при первом запуске.
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

    // оба формата: YAML пишется своим энкодером, и его ошибки JSON не покажет
    foreach ($builder->encode() as $name => $content) {
        file_put_contents(sprintf('%s/%s--%s.json', $directory, $case, basename($name, '.json')), $content);
        ++$written;
    }

    foreach ($builder->encode('', new YamlEncoder()) as $name => $content) {
        file_put_contents(sprintf('%s/%s--%s.yaml', $directory, $case, basename($name, '.json')), $content);
        ++$written;
    }
}

printf("Built %d documents in %s\n", $written, $directory);

$command = sprintf(
    'cd %s && npx --yes @redocly/cli@2 lint --config %s *--*.json *--*.yaml 2>&1',
    escapeshellarg($directory),
    escapeshellarg(basename($config)),
);

/**
 * Расхождения валидатора со спецификацией: подстрока сообщения => почему это не наша ошибка.
 */
const KNOWN_DEFECTS = [
    // JSON Schema 2020-12 объявляет $vocabulary объектом «URI => bool», Redocly ждёт строку
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

// в отчёте Redocly перечисляет каждый файл — оставляем итог
foreach ($output as $line) {
    if (str_contains($line, 'valid') || str_contains($line, 'error')) {
        echo $line, "\n";
    }
}
