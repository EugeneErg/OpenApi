<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\RequestBodies;

use EugeneErg\OpenApi\Process;
use stdClass;

final readonly class Encodings
{
    /** @var array<string, Encoding> */
    public array $items;

    public function __construct(Encoding ...$encodings)
    {
        /** @var array<string, Encoding> $encodings */
        $this->items = $encodings;
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
