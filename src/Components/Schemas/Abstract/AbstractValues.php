<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Abstract;

use EugeneErg\OpenApi\Process;
use stdClass;

abstract readonly class AbstractValues
{
    /** @var array<null|bool|float|int|self|string> */
    public array $items;

    public function __construct(bool|float|int|self|string|null ...$items)
    {
        $this->items = $items;
    }

    /**
     * @return array<null|array{}|bool|float|int|object|string>|stdClass
     */
    public function toNative(Process $process): array|stdClass
    {
        $result = [];

        foreach ($this->items as $item) {
            $result[] = $item instanceof self ? $item->toNative($process) : $item;
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
