<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Serialization;

use EugeneErg\OpenApi\Exceptions\InvalidDocumentOpenapiException;
use stdClass;

use function is_array;
use function is_object;
use function sprintf;

/**
 * Brings what a YAML parser returns to the same shape json_decode gives: maps as
 * stdClass, lists as arrays.
 */
final readonly class Structure
{
    /**
     * An object's fields with string keys.
     *
     * get_object_vars() returns array<mixed>: PHP casts a numeric property name to an
     * int, so the keys are normalised explicitly.
     *
     * @return array<string, mixed>
     */
    public static function vars(stdClass $value): array
    {
        $result = [];

        foreach (get_object_vars($value) as $key => $item) {
            $result[(string) $key] = $item;
        }

        return $result;
    }

    public static function toObject(mixed $value): stdClass
    {
        $result = self::normalize($value);

        if (!$result instanceof stdClass) {
            throw new InvalidDocumentOpenapiException(
                sprintf('Document must be a mapping at the top level, got %s.', get_debug_type($result)),
            );
        }

        return $result;
    }

    public static function normalize(mixed $value): mixed
    {
        if (is_object($value)) {
            $value = get_object_vars($value);
        } elseif (!is_array($value)) {
            return $value;
        }

        // An empty structure is indistinguishable: both `{}` and `[]` arrive as an empty
        // array. It stays a list, and Reader accepts both forms where it expects a map.
        if ($value === [] || array_is_list($value)) {
            return array_map(self::normalize(...), $value);
        }

        $result = [];

        foreach ($value as $key => $item) {
            $result[(string) $key] = self::normalize($item);
        }

        return (object) $result;
    }
}
