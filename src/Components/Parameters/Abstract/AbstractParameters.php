<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Parameters\Abstract;

use EugeneErg\OpenApi\Process;
use stdClass;

abstract readonly class AbstractParameters
{
    /** @var array<string, AbstractParameter> */
    public array $items;

    public function __construct(AbstractParameter ...$parameters)
    {
        /** @var array<string, AbstractParameter> $parameters */
        $this->items = $parameters;
    }

    /**
     * @return array<int, stdClass>
     */
    public function toArray(Process $process): array
    {
        $result = [];

        foreach ($this->items as $name => $parameter) {
            $result[] = (object) array_merge((array) $parameter->toObject($process), ['name' => $name]);
        }

        return $result;
    }
}
