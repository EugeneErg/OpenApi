<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components;

use EugeneErg\OpenApi\Components\Parameters\Parameter;
use EugeneErg\OpenApi\Process;
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

    public function sourceToObject(Process $process): stdClass
    {
        $result = [];

        foreach ($this->items as $name => $item) {
            $result[$name] = $item->toObject($process);
        }

        return (object) $result;
    }
}
