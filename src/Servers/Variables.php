<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Servers;

use EugeneErg\OpenApi\Support\NamedItems;
use stdClass;

final readonly class Variables
{
    use NamedItems;

    /** @var array<array-key, Variable> */
    public array $items;

    public function __construct(Variable ...$variables)
    {
        $this->items = self::named($variables);
    }

    public function toObject(): stdClass
    {
        $result = [];

        foreach ($this->items as $name => $item) {
            $result[$name] = $item->toObject();
        }

        return (object) $result;
    }
}
