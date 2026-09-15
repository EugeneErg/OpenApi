<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi;

use EugeneErg\OpenApi\Paths\Path;
use stdClass;

/**
 * Именованная карта Path Item Object.
 *
 * Используется там, где ключ — не шаблон пути: webhooks (имя вебхука) и
 * callbacks (runtime-выражение вроде `{$request.body#/callbackUrl}`).
 * Для секции paths есть наследник Paths, который дополнительно проверяет шаблоны.
 */
readonly class PathItems
{
    /** @var array<string, Path|Reference> */
    public array $items;

    public function __construct(Path|Reference ...$paths)
    {
        /** @var array<string, Path|Reference> $paths */
        $this->items = $paths;
    }

    /**
     * Использование по месту: если Path Item лежит в components.pathItems, здесь будет $ref.
     */
    public function toObject(Process $process): stdClass
    {
        $result = [];

        foreach ($this->items as $name => $path) {
            $result[$name] = $path instanceof Reference
                ? $path->toObject($process)
                : ($process->findPathItem($path) ?? $path->toObject($process));
        }

        return (object) $result;
    }

    /**
     * Объявление в components: разворачивается целиком, без ссылок на самого себя.
     */
    public function sourceToObject(Process $process): stdClass
    {
        $result = [];

        foreach ($this->items as $name => $path) {
            $result[$name] = $path->toObject($process);
        }

        return (object) $result;
    }
}
