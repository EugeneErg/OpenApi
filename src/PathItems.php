<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi;

use EugeneErg\OpenApi\Paths\Path;
use EugeneErg\OpenApi\Support\NamedItems;
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
    use NamedItems {
        fromArray as private fromNamedArray;
    }

    /** @var array<array-key, Path|Reference> */
    public array $items;

    public Extensions $extensions;

    /**
     * Расширения есть у Paths Object и Callback Object. В webhooks и
     * components.pathItems это обычная карта, и там они отклоняются.
     */
    public function __construct(?Extensions $extensions = null, Path|Reference ...$paths)
    {
        $this->items = self::named($paths);
        $this->extensions = $extensions ?? new Extensions();
    }

    /**
     * Карта с любыми именами, включая `{$request.body#/url}`, и расширениями `x-*`.
     *
     * Имя, совпадающее с «extensions», можно передать только так: именованным
     * аргументом оно попало бы в параметр расширений.
     *
     * @param array<array-key, mixed> $items
     */
    public static function fromArray(array $items, ?Extensions $extensions = null): static
    {
        /** @phpstan-ignore new.static */
        return new static($extensions, ...self::marked($items));
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

        return (object) $this->extensions->appendTo($result);
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
