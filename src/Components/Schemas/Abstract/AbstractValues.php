<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Abstract;

use EugeneErg\OpenApi\Process;
use stdClass;

abstract readonly class AbstractValues
{
    /** @var array<self|float|int|null|string|bool> */
    public array $items;

    public function __construct(null|self|int|float|string|bool ...$items)
    {
        $this->items = $items;
    }

    /**
     * @return array<float|int|null|string|bool|object|array{}>|stdClass
     */
    public function toNative(Process $process): array|stdClass
    {
        $result = [];

        foreach ($this->items as $item) {
            $result[] = $process->findExample($item) ?? ($item instanceof self ? $item->toNative($process) : $item);
        }

        return $result;
    }

    public function sourceToObject(Process $process): stdClass
    {
        $result = [];

        foreach ($this->items as $key => $item) {
            $result[$key] = $item instanceof self ? $item->toNative($process) : $item;
        }

        return (object) $result;
    }
}
