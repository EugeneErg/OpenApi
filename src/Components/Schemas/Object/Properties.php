<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Object;

use EugeneErg\OpenApi\Support\NamedItems;

final readonly class Properties
{
    use NamedItems;

    /** @var array<array-key, Property> */
    public array $items;

    public function __construct(Property ...$properties)
    {
        $this->items = self::named($properties);
    }
}
