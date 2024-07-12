<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components;

use EugeneErg\OpenApi\Paths;
use EugeneErg\OpenApi\Process;
use stdClass;

final readonly class Callbacks
{
    /** @var array<string, Paths> */
    public array $items;

    public function __construct(Paths ...$paths)
    {
        /** @var array<string, Paths> $paths */
        $this->items = $paths;
    }

    public function toObject(Process $process): stdClass
    {
        $result = [];

        foreach ($this->items as $name => $callback) {
            $result[$name] = $process->findCallback($callback) ?? $callback->toObject($process);
        }

        return (object) $result;
    }

    public function sourceToObject(Process $process): stdClass
    {
        $result = [];

        foreach ($this->items as $name => $callback) {
            $result[$name] = $callback->toObject($process);
        }

        return (object) $result;
    }
}
