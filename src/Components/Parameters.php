<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components;

use EugeneErg\OpenApi\Components\Parameters\Parameter;
use EugeneErg\OpenApi\Process;
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

    public function sourceToObject(Process $process): stdClass
    {
        $result = [];

        foreach ($this->items as $name => $item) {
            $result[$name] = $item->toObject($process);
        }

        return (object) $result;
    }
}
