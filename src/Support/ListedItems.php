<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Support;

use EugeneErg\OpenApi\Exceptions\InvalidArgumentOpenapiException;

use function is_string;
use function sprintf;

/**
 * A list container: the names of its items mean nothing and would quietly disappear when
 * written, so passing them is an error.
 *
 * @internal
 */
trait ListedItems
{
    /**
     * @template TItem
     *
     * @param array<array-key, TItem> $items
     *
     * @return list<TItem>
     */
    private static function listed(array $items): array
    {
        foreach ($items as $name => $item) {
            if (is_string($name)) {
                throw new InvalidArgumentOpenapiException(sprintf(
                    '%s is a list: its items have no names, got "%s".',
                    substr((string) strrchr('\\' . static::class, '\\'), 1),
                    $name,
                ));
            }
        }

        return array_values($items);
    }
}
