<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components;

use EugeneErg\OpenApi\Components\RequestBodies\RequestBody;
use EugeneErg\OpenApi\Process;
use stdClass;

final readonly class RequestBodies
{
    /** @var array<string, RequestBody> */
    public array $items;

    public function __construct(RequestBody ...$requestBodies)
    {
        /** @var array<string, RequestBody> $requestBodies */
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
