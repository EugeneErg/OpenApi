<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Links\Link;

use stdClass;

final readonly class Parameters
{
    /** @var array<string, Parameter> */
    public array $items;

    public function __construct(Parameter ...$parameters)
    {
        /** @var array<string, Parameter> $parameters */
        $this->items = $parameters;
    }

    public function toObject(): stdClass
    {
        $result = [];

        foreach ($this->items as $key => $item) {
            $result[$key] = $item->value;
        }

        return (object) $result;
    }
}
