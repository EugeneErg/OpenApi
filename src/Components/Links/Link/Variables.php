<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Links\Link;

use EugeneErg\OpenApi\Process;

final readonly class Variables
{
    /** @var array<string, Variable> */
    public array $items;

    public function __construct(Variable ...$variables)
    {
        /** @var array<string, Variable> $variables */
        $this->items = $variables;
    }

    /**
     * @return array<string, array{default: mixed, description?: string}>
     */
    public function toArray(Process $process): array
    {
        $result = [];

        foreach ($this->items as $name => $item) {
            $result[$name] = $item->toArray($process);
        }

        return $result;
    }
}
