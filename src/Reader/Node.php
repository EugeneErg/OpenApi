<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Reader;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValues;
use EugeneErg\OpenApi\Components\Schemas\Array\OpenapiArray;
use EugeneErg\OpenApi\Components\Schemas\Object\OpenapiObject;
use EugeneErg\OpenApi\Exceptions\InvalidArgumentOpenapiException;
use EugeneErg\OpenApi\Exceptions\InvalidDocumentOpenapiException;
use EugeneErg\OpenApi\Extensions;
use EugeneErg\OpenApi\Serialization\Structure;
use stdClass;

use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_scalar;
use function is_string;
use function sprintf;

/**
 * A node of the decoded document together with its path.
 *
 * The path is there for the error messages: "expected a string" is useless in the middle
 * of somebody else's two-thousand-line specification.
 *
 * Empty collections are disambiguated here as well: through ext-yaml `{}` and `[]` arrive
 * as the same value, while every accessor knows which of the two belongs in its place.
 */
final readonly class Node
{
    /**
     * @param bool $present false when the key is absent; a null in the document and an absent key differ
     */
    public function __construct(
        public mixed $value,
        public string $path = '',
        public bool $present = true,
        /**
         * Where to mark what has been read. Without a collector the node marks nothing:
         * strict reading asks the collector, not the nodes.
         */
        private ?Reads $reads = null,
    ) {
    }

    /**
     * A node of a rewritten form: the same keywords, laid out differently.
     *
     * What is read there counts as read here too, so a rewrite (a union of types, an enum,
     * the siblings of a `$ref`) does not hide an unknown keyword.
     */
    public function rewritten(stdClass $value, ?string $path = null): self
    {
        if ($this->reads !== null && $this->value instanceof stdClass) {
            $this->reads->rewrote($value, $this->value);
        }

        return new self($value, $path ?? $this->path, reads: $this->reads);
    }

    public function has(string $key): bool
    {
        return $this->value instanceof stdClass && property_exists($this->value, $key);
    }

    public function get(string $key): self
    {
        if (!$this->has($key)) {
            return new self(null, $this->child($key), present: false);
        }

        /** @var stdClass $value */
        $value = $this->value;
        $this->reads?->key($value, $key);

        return new self($value->{$key}, $this->child($key), reads: $this->reads);
    }

    /**
     * The keyword is read and dropped: it changes nothing — `uniqueItems` on a string
     * always holds, because a string is not an array. Strict reading complains about what
     * was not read, and this was.
     */
    public function dropped(string ...$keys): void
    {
        if (!$this->value instanceof stdClass) {
            return;
        }

        foreach ($keys as $key) {
            $this->reads?->key($this->value, $key);

            // dropped whole: the package never looked inside such a value
            if (property_exists($this->value, $key)) {
                $this->reads?->whole($this->value->{$key});
            }
        }
    }

    /**
     * The key is in the document, even if its value is null.
     */
    public function isPresent(): bool
    {
        return $this->present;
    }

    public function isMissing(): bool
    {
        return $this->value === null;
    }

    public function string(): string
    {
        if (!is_string($this->value)) {
            throw $this->unexpected('a string');
        }

        return $this->value;
    }

    public function stringOrNull(): ?string
    {
        return $this->isMissing() ? null : $this->string();
    }

    public function bool(): bool
    {
        if (!is_bool($this->value)) {
            throw $this->unexpected('a boolean');
        }

        return $this->value;
    }

    public function boolOr(bool $default): bool
    {
        return $this->isMissing() ? $default : $this->bool();
    }

    public function int(): int
    {
        if (!is_int($this->value)) {
            throw $this->unexpected('an integer');
        }

        return $this->value;
    }

    public function intOrNull(): ?int
    {
        return $this->isMissing() ? null : $this->int();
    }

    /**
     * The number as written: an integer stays an integer.
     *
     * A bound like `9223372036854775807` does not survive a float: a round trip through
     * one breaks the value.
     */
    public function numberOrNull(): float|int|null
    {
        if ($this->isMissing()) {
            return null;
        }

        return is_int($this->value) || is_float($this->value) ? $this->value : throw $this->unexpected('a number');
    }

    public function floatOrNull(): ?float
    {
        if ($this->isMissing()) {
            return null;
        }

        if (!is_int($this->value) && !is_float($this->value)) {
            throw $this->unexpected('a number');
        }

        return (float) $this->value;
    }

    /**
     * A map of key => node. An empty list is read here as an empty map.
     *
     * A key may turn out to be an int: PHP casts a numeric property name to an integer and
     * an array will not let it back to a string. The caller casts it itself.
     *
     * @return array<int|string, self>
     */
    public function map(): array
    {
        if ($this->isMissing() || $this->value === []) {
            return [];
        }

        if (!$this->value instanceof stdClass) {
            throw $this->unexpected('a mapping');
        }

        $result = [];

        // PHP hands a numeric property name back as an int: "200" would become 200
        foreach (Structure::vars($this->value) as $key => $item) {
            $this->reads?->key($this->value, (string) $key);
            $result[(string) $key] = new self($item, $this->child((string) $key), reads: $this->reads);
        }

        return $result;
    }

    /**
     * @return list<self>
     */
    public function list(): array
    {
        if ($this->isMissing()) {
            return [];
        }

        // an empty map from ext-yaml is indistinguishable from an empty list
        if ($this->value instanceof stdClass && Structure::vars($this->value) === []) {
            return [];
        }

        if (!is_array($this->value)) {
            throw $this->unexpected('a list');
        }

        $result = [];

        foreach (array_values($this->value) as $index => $item) {
            $result[] = new self($item, $this->child((string) $index), reads: $this->reads);
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    public function strings(): array
    {
        return array_map(static fn (self $item): string => $item->string(), $this->list());
    }

    /**
     * The map of an extensible object: Paths, Responses and Callback Object hold their
     * items and their extensions side by side, and `x-*` are not items there.
     *
     * An ordinary map has no such split: in components.headers the name
     * `x-common-marker-version` is the name of a component, not an extension.
     *
     * @return array<int|string, self>
     */
    public function extensibleMap(): array
    {
        return array_filter(
            $this->map(),
            static fn (int|string $name): bool => !str_starts_with((string) $name, 'x-'),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * The `x-*` extensions of this object; the prefix is stripped — Extensions adds it back.
     */
    public function extensions(): ?Extensions
    {
        if (!$this->value instanceof stdClass) {
            return null;
        }

        $items = [];

        foreach (Structure::vars($this->value) as $name => $value) {
            $name = (string) $name;

            if (str_starts_with($name, 'x-')) {
                // an extension value is any JSON, and there is nothing to parse in it
                $this->reads?->key($this->value, $name);
                $this->reads?->whole($value);
                $items[substr($name, 2)] = self::native($value, $this->child($name));
            }
        }

        if ($items === []) {
            return null;
        }

        try {
            return Extensions::fromArray($items);
        } catch (InvalidArgumentOpenapiException $exception) {
            throw new InvalidDocumentOpenapiException(sprintf('%s: %s', $this->path, $exception->getMessage()));
        }
    }

    public function unexpected(string $expected): InvalidDocumentOpenapiException
    {
        return new InvalidDocumentOpenapiException(sprintf(
            '%s: expected %s, got %s.',
            $this->path === '' ? 'Document root' : $this->path,
            $expected,
            get_debug_type($this->value),
        ));
    }

    /**
     * A child's path is a real JSON Pointer (RFC 6901), because that is what the registry
     * looks declared references up by: a path template `/users` inside a pointer has to be
     * written as `~1users`, or the separator cannot be told from the name.
     */
    private function child(string $key): string
    {
        return $this->path . '/' . str_replace(['~', '/'], ['~0', '~1'], $key);
    }

    /**
     * An extension value is any JSON value.
     */
    private static function native(mixed $value, string $path): AbstractValues|bool|float|int|string|null
    {
        if ($value instanceof stdClass) {
            $items = [];

            foreach (Structure::vars($value) as $name => $item) {
                $items[$name] = self::native($item, $path . '/' . $name);
            }

            return OpenapiObject::fromArray($items);
        }

        if (is_array($value)) {
            $items = [];

            foreach (array_values($value) as $index => $item) {
                $items[] = self::native($item, $path . '/' . $index);
            }

            return new OpenapiArray(...$items);
        }

        return is_scalar($value) || $value === null
            ? $value
            : throw new InvalidDocumentOpenapiException(sprintf('%s: expected a JSON value.', $path));
    }
}
