<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components;

use EugeneErg\OpenApi\Components\Parameters\ContentParameter;
use EugeneErg\OpenApi\Components\Parameters\Header\SchemaParameter;
use EugeneErg\OpenApi\Process;
use EugeneErg\OpenApi\Reference;
use stdClass;

final readonly class Headers
{
    /** @var array<string, ContentParameter|Reference|SchemaParameter> */
    public array $items;

    public function __construct(ContentParameter|Reference|SchemaParameter ...$headers)
    {
        /** @var array<string, ContentParameter|Reference|SchemaParameter> $headers */
        $this->items = $headers;
    }

    public function toObject(Process $process): stdClass
    {
        $result = [];

        foreach ($this->items as $name => $header) {
            $result[$name] = $header instanceof Reference
                ? $header->toObject($process)
                : ($process->findHeader($header) ?? $header->toObject($process));
        }

        return (object) $result;
    }

    public function sourceToObject(Process $process): stdClass
    {
        $result = [];

        foreach ($this->items as $name => $header) {
            $result[$name] = $header->toObject($process);
        }

        return (object) $result;
    }
}
