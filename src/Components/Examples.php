<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components;

use EugeneErg\OpenApi\Components\Examples\Example;
use EugeneErg\OpenApi\Process;
use EugeneErg\OpenApi\Reference;
use EugeneErg\OpenApi\Support\NamedItems;
use stdClass;

final readonly class Examples
{
    use NamedItems;

    /** @var array<array-key, Example|Reference> */
    public array $items;

    public function __construct(Example|Reference ...$examples)
    {
        $this->items = self::named($examples);
    }

    /**
     * Use in place: when the example lives in components, a $ref stands here.
     */
    public function toObject(Process $process): stdClass
    {
        $result = [];

        foreach ($this->items as $name => $item) {
            $result[$name] = $item instanceof Reference
                ? $item->toObject($process)
                : ($process->findExample($item) ?? $item->toObject($process));
        }

        return (object) $result;
    }

    /**
     * The declaration in components: the examples are written out in full, without
     * references to themselves.
     */
    public function sourceToObject(Process $process): stdClass
    {
        $result = [];

        foreach ($this->items as $name => $item) {
            $result[$name] = $item->toObject($process);
        }

        return (object) $result;
    }
}
