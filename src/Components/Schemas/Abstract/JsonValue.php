<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Abstract;

use EugeneErg\OpenApi\Components\Schemas\Object\OpenapiObject;
use EugeneErg\OpenApi\Serialization\Structure;
use stdClass;

use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

/**
 * Equality and kind of values by the rules of JSON Schema.
 *
 * `1` and `1.0` are one number, the order of an object's keys does not matter, and an
 * empty object differs from an empty list. The string form is there to look a value up in
 * a set in O(1).
 *
 * @internal
 */
final class JsonValue
{
    /**
     * The members of a value: the elements of a list, the properties of an object. Needed
     * where a value and a schema are read by the same code.
     *
     * @return array<array-key, mixed>
     */
    public static function members(mixed $value): array
    {
        return $value instanceof stdClass ? Structure::vars($value) : (array) $value;
    }

    public static function key(mixed $value): string
    {
        return json_encode(self::tagged($value), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * The kind of a value in JSON terms: integer counts as a special case of number.
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
     * Whether a number is representable as an integer: JSON does not tell 2 from 2.0.
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
