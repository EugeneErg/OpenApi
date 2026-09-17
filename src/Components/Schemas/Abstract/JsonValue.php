<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Abstract;

use EugeneErg\OpenApi\Components\Schemas\Object\OpenapiObject;
use stdClass;

use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

/**
 * Равенство и вид значений по правилам JSON Schema.
 *
 * `1` и `1.0` — одно число, порядок ключей объекта не важен, а пустой объект
 * и пустой список различаются. Строковое представление нужно, чтобы искать
 * значение в наборе за O(1).
 *
 * @internal
 */
final class JsonValue
{
    public static function key(mixed $value): string
    {
        return json_encode(self::tagged($value), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * Вид значения в терминах JSON: integer считается частным случаем number.
     */
    public static function kind(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => 'boolean',
            is_int($value), is_float($value) => 'number',
            is_string($value) => 'string',
            $value instanceof OpenapiObject, $value instanceof stdClass => 'object',
            default => 'array',
        };
    }

    /**
     * Представимо ли число как integer: JSON не различает 2 и 2.0.
     */
    public static function isInteger(mixed $value): bool
    {
        return is_int($value)
            || (is_float($value) && is_finite($value) && floor($value) === $value && abs($value) <= PHP_INT_MAX);
    }

    private static function tagged(mixed $value): mixed
    {
        if (self::isInteger($value) && is_float($value)) {
            $value = (int) $value;
        }

        if ($value instanceof AbstractValues) {
            $value = $value instanceof OpenapiObject
                ? (object) $value->items
                : array_values($value->items);
        }

        if ($value instanceof stdClass) {
            $pairs = [];

            foreach (get_object_vars($value) as $name => $item) {
                $pairs[(string) $name] = self::tagged($item);
            }

            ksort($pairs, SORT_STRING);

            return ['o', array_map(null, array_keys($pairs), array_values($pairs))];
        }

        if (is_array($value)) {
            return ['a', array_map(self::tagged(...), array_values($value))];
        }

        return ['s', $value];
    }
}
