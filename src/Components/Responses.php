<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components;

use EugeneErg\OpenApi\Components\Responses\Response;
use EugeneErg\OpenApi\Process;
use EugeneErg\OpenApi\Reference;
use stdClass;

final readonly class Responses
{
    /** @var array<string, Reference|Response> */
    public array $items;

    public function __construct(Reference|Response ...$responses)
    {
        /** @var array<string, Reference|Response> $responses */
        $this->items = $responses;
    }

    public function toObject(Process $process): stdClass
    {
        $result = [];

        foreach ($this->items as $name => $item) {
            // именованный аргумент не может начинаться с цифры,
            // поэтому коды пишутся как x200 / x4XX и здесь разворачиваются обратно
            if (preg_match('{^x(?:\d{3}|\dXX)$}', (string) $name) === 1) {
                $name = substr((string) $name, 1);
            }

            $result[$name] = $item instanceof Reference
                ? $item->toObject($process)
                : ($process->findResponse($item) ?? $item->toObject($process));
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
