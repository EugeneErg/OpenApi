<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\RequestBodies;

use EugeneErg\OpenApi\Process;
use EugeneErg\OpenApi\Support\NamedItems;
use stdClass;

final readonly class Encodings
{
    use NamedItems;

    /** @var array<array-key, Encoding> */
    public array $items;

    public function __construct(Encoding ...$encodings)
    {
        $this->items = self::named($encodings);
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
