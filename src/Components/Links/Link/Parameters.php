<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Links\Link;

use EugeneErg\OpenApi\Support\NamedItems;
use stdClass;

final readonly class Parameters
{
    use NamedItems;

    /** @var array<array-key, Parameter> */
    public array $items;

    public function __construct(Parameter ...$parameters)
    {
        $this->items = self::named($parameters);
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
