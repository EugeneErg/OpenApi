<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Servers;

use stdClass;

final readonly class Variables
{
    /** @var array<string, Variable> */
    public array $items;

    public function __construct(Variable ...$variables)
    {
        /** @var array<string, Variable> $variables */
        $this->items = $variables;
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
