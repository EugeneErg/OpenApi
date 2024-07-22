<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components;

use EugeneErg\OpenApi\Components\Responses\Response;
use EugeneErg\OpenApi\Process;
use stdClass;

final readonly class Responses
{
    /** @var array<array-key, Response> */
    public array $items;

    public function __construct(Response ...$responses)
    {
        /** @var array<string, Response> $responses */
        $this->items = $responses;
    }

    public function toObject(Process $process): stdClass
    {
        $result = [];

        foreach ($this->items as $name => $item) {
            if (preg_match('{^x\d{3}$}', (string) $name) === 1) {
                $name = substr((string) $name, 1);
            }

            $result[$name] = $process->findResponse($item) ?? $item->toObject($process);
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
