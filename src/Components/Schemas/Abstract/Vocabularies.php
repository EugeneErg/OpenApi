<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Abstract;

use stdClass;

/**
 * $vocabulary: карта «URI словаря => обязателен ли он».
 *
 * URI нельзя передать именованным аргументом, поэтому используется распаковка:
 * `new Vocabularies(...['https://json-schema.org/draft/2020-12/vocab/core' => true])`.
 */
final readonly class Vocabularies
{
    /** @var array<string, bool> */
    public array $items;

    public function __construct(bool ...$vocabularies)
    {
        /** @var array<string, bool> $vocabularies */
        $this->items = $vocabularies;
    }

    public function toObject(): stdClass
    {
        return (object) $this->items;
    }
}
