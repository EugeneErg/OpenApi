<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi;

use EugeneErg\OpenApi\Exceptions\Place;
use EugeneErg\OpenApi\Paths\Path;
use EugeneErg\OpenApi\Support\NamedItems;
use stdClass;

/**
 * A named map of Path Item Objects.
 *
 * Used where the key is not a path template: webhooks (the webhook's name) and callbacks
 * (a runtime expression such as `{$request.body#/callbackUrl}`). The paths section has a
 * subclass of its own, Paths, which checks the templates as well.
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
     * A Paths Object and a Callback Object have extensions. In webhooks and
     * components.pathItems it is an ordinary map, and there they are rejected.
     */
    public function __construct(?Extensions $extensions = null, Path|Reference ...$paths)
    {
        $this->items = self::named($paths);
        $this->extensions = $extensions ?? new Extensions();
    }

    /**
     * A map with any names at all, `{$request.body#/url}` included, plus the `x-*`
     * extensions.
     *
     * A name that happens to be "extensions" can only be passed this way: as a named
     * argument it would land in the extensions parameter.
     *
     * @param array<array-key, mixed> $items
     */
    public static function fromArray(array $items, ?Extensions $extensions = null): static
    {
        /** @phpstan-ignore new.static */
        return new static($extensions, ...self::marked($items));
    }

    /**
     * Use in place: when the Path Item lives in components.pathItems, a $ref stands here.
     */
    public function toObject(Process $process): stdClass
    {
        $result = [];

        foreach ($this->items as $name => $path) {
            $result[$name] = Place::in(
                static fn (): stdClass => $path instanceof Reference
                    ? $path->toObject($process)
                    : ($process->findPathItem($path) ?? $path->toObject($process)),
                $name,
            );
        }

        return (object) $this->extensions->appendTo($result);
    }

    /**
     * The declaration in components: written out in full, without references to itself.
     */
    public function sourceToObject(Process $process): stdClass
    {
        $result = [];

        foreach ($this->items as $name => $path) {
            $result[$name] = Place::in(static fn (): stdClass => $path->toObject($process), $name);
        }

        return (object) $result;
    }
}
