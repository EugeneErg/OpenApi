<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Abstract;

use EugeneErg\OpenApi\Support\NamedItems;
use stdClass;

/**
 * $vocabulary: a map of vocabulary URI => whether it is required.
 *
 * A URI cannot be passed as a named argument, so unpacking is used instead:
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
