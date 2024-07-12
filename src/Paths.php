<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi;

use EugeneErg\OpenApi\Paths\Path;
use stdClass;

final readonly class Paths
{
    /** @var array<string, Path> */
    public array $items;

    public function __construct(Path ...$paths)
    {
        /** @var array<string, Path> $paths */
        $this->items = $paths;
    }

    public function toObject(Process $process): stdClass
    {
        $result = [];

        foreach ($this->items as $name => $path) {
            $result[$name] = $path->toObject($process);
        }

        return (object) $result;
    }
}
