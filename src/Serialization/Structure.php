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
     * As deep as json_decode() goes by default: a document deeper than this is refused
     * rather than read, and the same bound keeps a cyclic structure from being walked
     * for ever.
     */
    private const int MAX_DEPTH = 512;

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
        return self::walk($value, 0);
    }

    private static function walk(mixed $value, int $depth): mixed
    {
        if ($depth > self::MAX_DEPTH) {
            // A YAML anchor may point at the node that carries it, and ext-yaml returns
            // that as a structure with no end: `$parsed['a']['b'] === $parsed['a']`.
            // Walking it never finishes, and there is nothing to write back either —
            // neither JSON nor the objects of this package can express a cycle in data.
            throw new InvalidDocumentOpenapiException(sprintf(
                'The document nests deeper than %d levels. An anchor that refers to itself reads as '
                . 'a structure without an end, and no document can be written back from one.',
                self::MAX_DEPTH,
            ));
        }

        if (is_object($value)) {
            $value = get_object_vars($value);
        } elseif (!is_array($value)) {
            return $value;
        }

        // An empty structure is indistinguishable: both `{}` and `[]` arrive as an empty
        // array. It stays a list, and Reader accepts both forms where it expects a map.
        if ($value === [] || array_is_list($value)) {
            return array_map(static fn (mixed $item): mixed => self::walk($item, $depth + 1), $value);
        }

        $result = [];

        foreach ($value as $key => $item) {
            $result[(string) $key] = self::walk($item, $depth + 1);
        }

        return (object) $result;
    }
}
