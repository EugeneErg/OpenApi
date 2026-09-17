<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Support;

use EugeneErg\OpenApi\Exceptions\InvalidArgumentOpenapiException;

use function is_int;
use function sprintf;
use function strlen;

/**
 * The names of the items of a map container.
 *
 * The containers take names as named arguments: `new Properties(id: $id)` or
 * `new Properties(...['created-at' => $at])`. That spelling has one PHP trap: an array key
 * of '7' or '-1' is stored as an integer, and unpacking turns it into a positional
 * argument — the name is lost, and together with named arguments PHP fails outright.
 *
 * So a positional item in a map is always an error, and fromArray() is there for
 * arbitrary names. It goes through the same constructor, so every check of types and
 * invariants applies here as well: the names are merely marked so that PHP keeps them as
 * strings, and the constructor takes the mark off.
 *
 * @internal the mark is an implementation detail; only fromArray() is public
 */
trait NamedItems
{
    private const string NAME_MARK = "\0eugene-erg/open-api:name:";

    /**
     * A container with any names at all, '7' and '-1' included.
     *
     * @param array<array-key, mixed> $items
     */
    public static function fromArray(array $items): static
    {
        /** @phpstan-ignore argument.type, new.static */
        return new static(...self::marked($items));
    }

    /**
     * Marks the names so that PHP keeps them as strings through the unpacking.
     *
     * @param array<array-key, mixed> $items
     *
     * @return array<string, mixed>
     */
    private static function marked(array $items): array
    {
        $marked = [];

        foreach ($items as $name => $item) {
            $marked[self::NAME_MARK . $name] = $item;
        }

        return $marked;
    }

    /**
     * Takes the marks off and rejects the items that have no name.
     *
     * A key of the result may turn out to be an int again ('7' => 7): that is how PHP
     * keeps such names in any array, so the consumers cast the key to a string.
     *
     * @template TItem
     *
     * @param array<array-key, TItem> $items
     *
     * @return array<array-key, TItem>
     */
    private static function named(array $items): array
    {
        $result = [];

        foreach ($items as $name => $item) {
            if (is_int($name)) {
                throw self::positional();
            }

            if (str_starts_with($name, self::NAME_MARK)) {
                $name = substr($name, strlen(self::NAME_MARK));
            }

            $result[$name] = $item;
        }

        return $result;
    }

    private static function positional(): InvalidArgumentOpenapiException
    {
        $class = substr((string) strrchr('\\' . static::class, '\\'), 1);

        return new InvalidArgumentOpenapiException(sprintf(
            '%1$s needs a name for every item. Integer-like names such as "7" or "-1" turn into '
            . 'positions when PHP spreads an array: pass such names through %1$s::fromArray().',
            $class,
        ));
    }
}
