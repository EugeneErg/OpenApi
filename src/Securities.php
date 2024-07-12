<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi;

use EugeneErg\OpenApi\Securities\SecuritySchemes;
use stdClass;

final readonly class Securities
{
    /** @var SecuritySchemes[] */
    public array $items;

    public function __construct(SecuritySchemes ...$securities)
    {
        $this->items = $securities;
    }

    /**
     * @return array<int, stdClass>
     */
    public function toArray(Process $process): array
    {
        $result = [];

        foreach ($this->items as $schema) {
            $result[] = $schema->toObject($process);
        }

        return $result;
    }
}
