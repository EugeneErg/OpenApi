<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components;

use EugeneErg\OpenApi\PathItems;
use EugeneErg\OpenApi\Process;
use EugeneErg\OpenApi\Reference;
use EugeneErg\OpenApi\Support\NamedItems;
use stdClass;

final readonly class Callbacks
{
    use NamedItems;

    /** @var array<array-key, PathItems|Reference> */
    public array $items;

    public function __construct(PathItems|Reference ...$paths)
    {
        $this->items = self::named($paths);
    }

    public function toObject(Process $process): stdClass
    {
        $result = [];

        foreach ($this->items as $name => $callback) {
            $result[$name] = $callback instanceof Reference
                ? $callback->toObject($process)
                : ($process->findCallback($callback) ?? $callback->toObject($process));
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
