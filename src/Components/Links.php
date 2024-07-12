<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components;

use EugeneErg\OpenApi\Components\Links\Link;
use EugeneErg\OpenApi\Process;
use stdClass;

final readonly class Links
{
    /** @var array<string, Link> */
    public array $items;

    public function __construct(Link ...$links)
    {
        /** @var array<string, Link> $links */
        $this->items = $links;
    }

    public function toObject(Process $process): stdClass
    {
        $result = [];

        foreach ($this->items as $name => $item) {
            $result[$name] = $process->findLink($item) ?? $item->toObject($process);
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
