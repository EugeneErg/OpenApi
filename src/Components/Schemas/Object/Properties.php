<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Object;

final readonly class Properties
{
    /** @var array<string, Property> */
    public array $items;

    public function __construct(Property ...$properties)
    {
        /** @var array<string, Property> $properties */
        $this->items = $properties;
    }
}
