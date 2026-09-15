<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components;

use EugeneErg\OpenApi\Components\RequestBodies\RequestBody;
use EugeneErg\OpenApi\Process;
use EugeneErg\OpenApi\Reference;
use stdClass;

final readonly class RequestBodies
{
    /** @var array<string, Reference|RequestBody> */
    public array $items;

    public function __construct(Reference|RequestBody ...$requestBodies)
    {
        /** @var array<string, Reference|RequestBody> $requestBodies */
        $this->items = $requestBodies;
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
