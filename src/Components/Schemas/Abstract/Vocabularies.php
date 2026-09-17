<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Abstract;

use EugeneErg\OpenApi\Support\NamedItems;
use stdClass;

/**
 * $vocabulary: карта «URI словаря => обязателен ли он».
 *
 * URI нельзя передать именованным аргументом, поэтому используется распаковка:
 * `new Vocabularies(...['https://json-schema.org/draft/2020-12/vocab/core' => true])`.
 */
final readonly class Vocabularies
{
    use NamedItems;

    /** @var array<array-key, bool> */
    public array $items;

    public function __construct(bool ...$vocabularies)
    {
        $this->items = self::named($vocabularies);
    }

    public function toObject(): stdClass
    {
        return (object) $this->items;
    }
}
