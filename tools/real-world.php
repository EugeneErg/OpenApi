<?php

declare(strict_types = 1);

/*
 * Проверка на крупных публичных спецификациях: чтение → запись → смысловое сравнение.
 *
 *     composer real-world               # все
 *     composer real-world -- stripe     # выборочно
 *
 * Файлы скачиваются один раз в системный временный каталог. Отчёт перечисляет,
 * что документ не удалось прочитать или что в нём потерялось.
 */

use EugeneErg\OpenApi\Builder;
use EugeneErg\OpenApi\Reader;
use EugeneErg\OpenApi\Serialization\JsonDecoder;
use EugeneErg\OpenApi\Serialization\YamlDecoder;
use Tests\Support\SemanticDiff;

require __DIR__ . '/../vendor/autoload.php';

ini_set('memory_limit', '4G');

$specifications = [
    'github' => 'https://raw.githubusercontent.com/github/rest-api-description/main/descriptions/api.github.com/api.github.com.json',
    'stripe' => 'https://raw.githubusercontent.com/stripe/openapi/master/openapi/spec3.json',
    'twilio' => 'https://raw.githubusercontent.com/twilio/twilio-oai/main/spec/json/twilio_api_v2010.json',
    'asana' => 'https://raw.githubusercontent.com/Asana/openapi/master/defs/asana_oas.yaml',
    // 3.1: массивы в type, тип null, роли, словарь JSON Schema 2020-12
    'github-next' => 'https://raw.githubusercontent.com/github/rest-api-description/main/descriptions-next/api.github.com/api.github.com.json',
    'discord' => 'https://raw.githubusercontent.com/discord/discord-api-spec/main/specs/openapi.json',
    'airflow' => 'https://raw.githubusercontent.com/apache/airflow/main/airflow-core/src/airflow/api_fastapi/core_api/openapi/v2-rest-api-generated.yaml',
    'museum' => 'https://raw.githubusercontent.com/Redocly/museum-openapi-example/main/openapi.yaml',
    // документы, написанные разными генераторами: у каждого свои привычки
    'immich' => 'https://raw.githubusercontent.com/immich-app/immich/main/open-api/immich-openapi-specs.json', // NestJS
    'authentik' => 'https://raw.githubusercontent.com/goauthentik/authentik/main/schema.yml', // drf-spectacular
    'kratos' => 'https://raw.githubusercontent.com/ory/kratos/master/spec/api.json', // go-swagger
    'kubernetes' => 'https://raw.githubusercontent.com/kubernetes/kubernetes/master/api/openapi-spec/v3/apis__apps__v1_openapi.json', // kube-openapi
    'sonarr' => 'https://raw.githubusercontent.com/Sonarr/Sonarr/develop/src/Sonarr.Api.V3/openapi.json', // .NET
    'firefly' => 'https://raw.githubusercontent.com/hyperledger/firefly/main/doc-site/docs/swagger/swagger.yaml', // Hyperledger FireFly
    'elasticsearch' => 'https://raw.githubusercontent.com/elastic/elasticsearch-specification/main/output/openapi/elasticsearch-serverless-openapi.json',
];

/**
 * Документы, которые спецификация не допускает: имя => [часть сообщения, почему].
 *
 * Прочитать их нельзя — объекты пакета такого не выражают, — а молча починить
 * значит соврать. Поэтому здесь проверяется отказ и его формулировка: это она
 * достаётся тому, кто такой документ принесёт.
 */
const KNOWN_INVALID = [
    // ASP.NET catch-all route: шаблон пути «/», а параметр объявлен путевым
    'sonarr' => [
        'declares path parameter "path", which does not appear in the template',
        'a path parameter that the template does not declare',
    ],
    // «Each name MUST correspond to a security scheme which is declared in components»
    'kratos' => [
        'security scheme "sessionToken" is not declared in components.securitySchemes',
        'a security requirement naming an undeclared scheme',
    ],
];

$selected = array_slice($argv, 1);
$cache = sys_get_temp_dir() . '/eugene-erg-openapi-real-world';
$failed = false;

if (!is_dir($cache) && !mkdir($cache, 0o775, true) && !is_dir($cache)) {
    fwrite(STDERR, "Cannot create {$cache}\n");

    exit(2);
}

foreach ($specifications as $name => $url) {
    if ($selected !== [] && !in_array($name, $selected, true)) {
        continue;
    }

    $file = $cache . '/' . $name . '.' . pathinfo($url, PATHINFO_EXTENSION);

    if (!is_file($file)) {
        $content = @file_get_contents($url);

        if ($content === false) {
            echo "{$name}: cannot download {$url}\n";
            $failed = true;

            continue;
        }

        file_put_contents($file, $content);
    }

    $yaml = str_ends_with($file, '.yaml') || str_ends_with($file, '.yml');

    if ($yaml && !YamlDecoder::isAvailable()) {
        echo "{$name}: skipped, ext-yaml is not installed\n";

        continue;
    }

    $decoder = $yaml ? new YamlDecoder() : new JsonDecoder();
    $content = (string) file_get_contents($file);
    $started = microtime(true);

    try {
        $built = (new Builder(...['openapi.json' => Reader::read($content, $decoder)]))->prepareToSave();
        $diff = new SemanticDiff($decoder->decode($content), json_decode((string) json_encode($built['openapi.json'])));
    } catch (Throwable $exception) {
        [$expected, $why] = KNOWN_INVALID[$name] ?? [null, null];

        if ($expected !== null && str_contains($exception->getMessage(), $expected)) {
            printf("%s: rejected as expected — %s (%.1fs)\n", $name, $why, microtime(true) - $started);

            continue;
        }

        echo "{$name}: FAILED — {$exception->getMessage()}\n";
        $failed = true;

        continue;
    }

    if (isset(KNOWN_INVALID[$name])) {
        printf("%s: FAILED — was expected to be rejected: %s\n", $name, KNOWN_INVALID[$name][1]);
        $failed = true;

        continue;
    }

    printf(
        "%s: %s%s (%.1fs)\n",
        $name,
        $diff->differences === [] ? 'OK' : count($diff->differences) . ' differences',
        $diff->rewritten === 0 ? '' : sprintf(', %d rewritten', $diff->rewritten),
        microtime(true) - $started,
    );

    foreach (array_slice($diff->differences, 0, 10) as $difference) {
        echo "    {$difference}\n";
    }

    $failed = $failed || $diff->differences !== [];
}

exit($failed ? 1 : 0);
