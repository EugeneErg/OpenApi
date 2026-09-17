<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components;

use EugeneErg\OpenApi\Components\Links\Link;
use EugeneErg\OpenApi\Process;
use EugeneErg\OpenApi\Reference;
use EugeneErg\OpenApi\Support\NamedItems;
use stdClass;

final readonly class Links
{
    use NamedItems;

    /** @var array<array-key, Link|Reference> */
    public array $items;

    public function __construct(Link|Reference ...$links)
    {
        $this->items = self::named($links);
    }

    public function toObject(Process $process): stdClass
    {
        $result = [];

        foreach ($this->items as $name => $item) {
            $result[$name] = $item instanceof Reference
                ? $item->toObject($process)
                : ($process->findLink($item) ?? $item->toObject($process));
        }

        return (object) $result;
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
