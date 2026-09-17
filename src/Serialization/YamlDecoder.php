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
 * Parsing YAML through the ext-yaml extension.
 *
 * The package deliberately has no parser of its own: YAML 1.2 means anchors, aliases,
 * block scalars and flow syntax, and an incomplete implementation would quietly spoil
 * somebody else's document instead of refusing to read it.
 *
 * When ext-yaml is unavailable, implement DecoderInterface over symfony/yaml — it is a
 * few lines, and README has an example. Reading YAML is not closed off by that.
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
     * The YAML 1.2 core schema, which is what OpenAPI requires.
     *
     * ext-yaml resolves plain scalars by the YAML 1.1 rules and quietly spoils the data:
     * `521621,621373` becomes the number 521621621373, `12:30` the number 750, `no`, `on`
     * and `y` become booleans (in keys as well), `012` becomes octal 10, and with
     * yaml.decode_timestamp on, dates turn into numbers. The callbacks are handed the
     * scalar's original text, and here it is resolved anew by YAML 1.2: whatever is not a
     * number, a boolean or a null there stays a string.
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
            // beyond int a number stays a number, as it does in JSON
            // in YAML 1.2 a leading zero does not make a number octal
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
