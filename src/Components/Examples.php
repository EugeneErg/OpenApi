<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components;

use EugeneErg\OpenApi\Components\Examples\Example;
use EugeneErg\OpenApi\Process;
use EugeneErg\OpenApi\Reference;
use stdClass;

final readonly class Examples
{
    /** @var array<string, Example|Reference> */
    public array $items;

    public function __construct(Example|Reference ...$examples)
    {
        /** @var array<string, Example|Reference> $examples */
        $this->items = $examples;
    }

    /**
     * Использование по месту: если пример лежит в components, здесь будет $ref.
     */
    public function toObject(Process $process): stdClass
    {
        $result = [];

        foreach ($this->items as $name => $item) {
            $result[$name] = $item instanceof Reference
                ? $item->toObject($process)
                : ($process->findExample($item) ?? $item->toObject($process));
        }

        return (object) $result;
    }

    /**
     * Объявление в components: примеры разворачиваются целиком, без ссылок на самих себя.
     */
    public function sourceToObject(Process $process): stdClass
    {
        $result = [];

        foreach ($this->items as $name => $item) {
            $result[$name] = $item->toObject($process);
        }

        return (object) $result;
    }
}
