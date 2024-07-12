<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\RequestBodies;

use EugeneErg\OpenApi\Process;
use stdClass;

final readonly class Contents
{
    /** @var array<string, Content> */
    public array $items;

    public function __construct(Content ...$contents)
    {
        /** @var array<string, Content> $contents */
        $this->items = $contents;
    }

    public function toObject(Process $process): stdClass
    {
        $result = [];

        foreach ($this->items as $name => $item) {
            $result[$name] = $item->toObject($process);
        }

        return (object) $result;
    }
}
