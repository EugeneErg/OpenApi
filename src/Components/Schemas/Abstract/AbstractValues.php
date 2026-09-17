<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Abstract;

use EugeneErg\OpenApi\Exceptions\InvalidArgumentOpenapiException;
use EugeneErg\OpenApi\Process;
use EugeneErg\OpenApi\Support\ListedItems;
use EugeneErg\OpenApi\Support\NamedItems;
use stdClass;

/**
 * JSON-значение-контейнер: объект (OpenapiObject) или список (все остальные).
 */
abstract readonly class AbstractValues
{
    use ListedItems;
    use NamedItems {
        fromArray as protected fromNamedArray;
    }

    /** @var array<null|bool|float|int|self|string> */
    public array $items;

    public function __construct(bool|float|int|self|string|null ...$items)
    {
        $this->items = static::isMap() ? self::named($items) : self::listed($items);
    }

    /**
     * Объект — из карты с любыми именами, включая '7' и '-1'; список — из списка.
     *
     * @param array<array-key, mixed> $items
     */
    public static function fromArray(array $items): static
    {
        if (static::isMap()) {
            return static::fromNamedArray($items);
        }

        if (!array_is_list($items)) {
            throw new InvalidArgumentOpenapiException('A list of values cannot have named items.');
        }

        /** @phpstan-ignore new.static */
        return new static(...$items);
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

    protected static function isMap(): bool
    {
        return false;
    }
}
