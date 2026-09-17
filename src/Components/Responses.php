<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components;

use EugeneErg\OpenApi\Components\Responses\Response;
use EugeneErg\OpenApi\Extensions;
use EugeneErg\OpenApi\Process;
use EugeneErg\OpenApi\Reference;
use EugeneErg\OpenApi\Support\NamedItems;
use stdClass;

final readonly class Responses
{
    use NamedItems {
        fromArray as private fromNamedArray;
    }

    /** @var array<array-key, Reference|Response> */
    public array $items;

    public Extensions $extensions;

    /**
     * Расширения есть у Responses Object операции. В components.responses
     * это обычная карта, и там они отклоняются.
     */
    public function __construct(?Extensions $extensions = null, Reference|Response ...$responses)
    {
        $this->items = self::named($responses);
        $this->extensions = $extensions ?? new Extensions();
    }

    /**
     * Карта с любыми именами, включая `200`, и расширениями `x-*`.
     *
     * Имя, совпадающее с «extensions», можно передать только так: именованным
     * аргументом оно попало бы в параметр расширений.
     *
     * @param array<array-key, mixed> $items
     */
    public static function fromArray(array $items, ?Extensions $extensions = null): static
    {
        /** @phpstan-ignore argument.type */
        return new self($extensions, ...self::marked($items));
    }

    public function toObject(Process $process): stdClass
    {
        $result = [];

        foreach ($this->items as $name => $item) {
            // именованный аргумент не может начинаться с цифры,
            // поэтому коды пишутся как x200 / x4XX и здесь разворачиваются обратно
            if (preg_match('{^x(?:\d{3}|\dXX)$}', (string) $name) === 1) {
                $name = substr((string) $name, 1);
            }

            $result[$name] = $item instanceof Reference
                ? $item->toObject($process)
                : ($process->findResponse($item) ?? $item->toObject($process));
        }

        return (object) $this->extensions->appendTo($result);
    }

    public function sourceToObject(Process $process): stdClass
    {
        $result = [];

        foreach ($this->items as $name => $item) {
            $result[$name] = $item->toObject($process);
        }

        return (object) $result;
    }
}
