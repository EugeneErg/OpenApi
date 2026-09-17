<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Serialization;

use EugeneErg\OpenApi\Exceptions\InvalidDocumentOpenapiException;
use RuntimeException;
use stdClass;

use function function_exists;
use function in_array;
use function is_scalar;

/**
 * Разбор YAML через расширение ext-yaml.
 *
 * Собственного парсера в пакете нет намеренно: YAML 1.2 — это якоря, ссылки,
 * блочные скаляры и поточный синтаксис, и неполная реализация молча испортила бы
 * чужой документ вместо того, чтобы отказаться его читать.
 *
 * Если ext-yaml недоступен, реализуйте DecoderInterface поверх symfony/yaml —
 * это несколько строк, пример есть в README. Чтение YAML от этого не закрыто.
 */
final readonly class YamlDecoder implements DecoderInterface
{
    public static function isAvailable(): bool
    {
        return function_exists('yaml_parse');
    }

    public function decode(string $content): stdClass
    {
        if (!self::isAvailable()) {
            throw new RuntimeException(
                'Reading YAML requires the ext-yaml extension. Install it, or implement '
                . 'DecoderInterface over another parser such as symfony/yaml.',
            );
        }

        $count = 0;
        $result = @yaml_parse($content, 0, $count, self::coreSchema());

        if ($result === false) {
            throw new InvalidDocumentOpenapiException('Document is not valid YAML.');
        }

        return Structure::toObject($result);
    }

    /**
     * Базовая схема YAML 1.2, которой требует OpenAPI.
     *
     * ext-yaml разрешает простые скаляры по правилам YAML 1.1 и молча портит данные:
     * `521621,621373` становится числом 521621621373, `12:30` — числом 750,
     * `no`, `on`, `y` — логическими значениями (даже в ключах), `012` — восьмеричным 10,
     * а при включённом yaml.decode_timestamp даты превращаются в числа. Колбэки
     * получают исходный текст скаляра, и здесь он разрешается заново по YAML 1.2:
     * всё, что там не число, не логическое и не null, остаётся строкой.
     *
     * @return array<string, callable(mixed, string, int): mixed>
     */
    private static function coreSchema(): array
    {
        $text = static fn (mixed $value): string => is_scalar($value) ? (string) $value : '';

        return [
            'tag:yaml.org,2002:int' => static fn (mixed $value): float|int|string => self::integer($text($value)),
            'tag:yaml.org,2002:float' => static fn (mixed $value): float|string => self::float($text($value)),
            'tag:yaml.org,2002:bool' => static fn (mixed $value): bool|string => match ($text($value)) {
                'true', 'True', 'TRUE' => true,
                'false', 'False', 'FALSE' => false,
                default => $text($value),
            },
            'tag:yaml.org,2002:null' => static fn (mixed $value): ?string => in_array(
                $text($value),
                ['null', 'Null', 'NULL', '~', ''],
                true,
            ) ? null : $text($value),
            'tag:yaml.org,2002:timestamp' => $text,
        ];
    }

    private static function integer(string $value): float|int|string
    {
        if (preg_match('{^[-+]?[0-9]+$}', $value) === 1) {
            // за пределами int число остаётся числом, как и в JSON
            // в YAML 1.2 ведущий ноль не делает число восьмеричным
            $integer = filter_var(preg_replace('{^([-+]?)0+(?=[0-9])}', '$1', $value), FILTER_VALIDATE_INT);

            return $integer === false ? (float) $value : $integer;
        }

        if (preg_match('{^0o([0-7]+)$}', $value, $match) === 1) {
            return octdec($match[1]);
        }

        if (preg_match('{^0x([0-9a-fA-F]+)$}', $value, $match) === 1) {
            return hexdec($match[1]);
        }

        return $value;
    }

    private static function float(string $value): float|string
    {
        return match (true) {
            preg_match('{^[-+]?(\.[0-9]+|[0-9]+(\.[0-9]*)?)([eE][-+]?[0-9]+)?$}', $value) === 1 => (float) $value,
            preg_match('{^[-+]?\.(inf|Inf|INF)$}', $value) === 1 => str_starts_with($value, '-') ? -INF : INF,
            preg_match('{^\.(nan|NaN|NAN)$}', $value) === 1 => NAN,
            default => $value,
        };
    }
}
