<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components;

use EugeneErg\OpenApi\Components\Responses\Response;
use EugeneErg\OpenApi\Exceptions\Place;
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
     * An operation's Responses Object has extensions. In components.responses it is an
     * ordinary map, and there they are rejected.
     */
    public function __construct(?Extensions $extensions = null, Reference|Response ...$responses)
    {
        $this->items = self::named($responses);
        $this->extensions = $extensions ?? new Extensions();
    }

    /**
     * A map with any names at all, `200` included, plus the `x-*` extensions.
     *
     * A name that happens to be "extensions" can only be passed this way: as a named
     * argument it would land in the extensions parameter.
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
            // a named argument cannot start with a digit, so the codes are written as
            // x200 / x4XX and are turned back here
            if (preg_match('{^x(?:\d{3}|\dXX)$}', (string) $name) === 1) {
                $name = substr((string) $name, 1);
            }

            $result[$name] = Place::in(
                static fn (): stdClass => $item instanceof Reference
                    ? $item->toObject($process)
                    : ($process->findResponse($item) ?? $item->toObject($process)),
                $name,
            );
        }

        return (object) $this->extensions->appendTo($result);
    }

    public function sourceToObject(Process $process): stdClass
    {
        $result = [];

        foreach ($this->items as $name => $item) {
            $result[$name] = Place::in(static fn (): stdClass => $item->toObject($process), $name);
        }

        return (object) $result;
    }
}
