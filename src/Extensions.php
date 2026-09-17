<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValues;
use EugeneErg\OpenApi\Components\Schemas\Object\OpenapiObject;
use EugeneErg\OpenApi\Exceptions\InvalidArgumentOpenapiException;
use EugeneErg\OpenApi\Serialization\Structure;
use EugeneErg\OpenApi\Support\NamedItems;
use stdClass;

/**
 * The extensions of the specification — the `x-*` fields.
 *
 * The specification asks exactly one thing of the name: that it start with `x-`. This
 * prefix is always added, so the name is written without it:
 *
 *     new Extensions(internal: true)                   // x-internal
 *     new Extensions(...['code-samples' => $samples])  // x-code-samples
 *     new Extensions(...['x-legacy' => $value])        // x-x-legacy
 *
 * The rule is one and has no exceptions, so every field of the document has exactly one
 * spelling here, and `x-x-legacy`, which the specification allows, is expressed as
 * `x-legacy`. The other side of it: the prefix is never written by hand — written, it
 * doubles.
 *
 * Names such as `7` are passed through fromArray(), as in every other map. A value is any
 * JSON value: a scalar, null, or a container (`OpenapiObject`, `OpenapiArray`).
 *
 * The 3.1 specification reserved the `x-oai-` and `x-oas-` prefixes for the OpenAPI
 * Initiative: the package accepts them — a document with them is legal — but an extension
 * of one's own is better named otherwise.
 */
final readonly class Extensions
{
    use NamedItems;

    /** @var array<array-key, null|AbstractValues|bool|float|int|string> the names without the prefix */
    public array $items;

    public function __construct(AbstractValues|bool|float|int|string|null ...$extensions)
    {
        $this->items = self::named($extensions);

        foreach (array_keys($this->items) as $name) {
            if ((string) $name === '') {
                throw new InvalidArgumentOpenapiException('Extension name must not be empty.');
            }
        }
    }

    /**
     * Appends the extensions after the object's fields.
     *
     * A subclass adds to what its parent printed, so the extensions are moved to the end
     * on every call: otherwise they would end up in the middle of the object.
     *
     * @param array<array-key, mixed>|stdClass $fields
     *
     * @return array<array-key, mixed>
     */
    public function appendTo(array|stdClass $fields): array
    {
        $fields = $fields instanceof stdClass ? Structure::vars($fields) : $fields;

        foreach ($this->items as $name => $value) {
            unset($fields['x-' . $name]);

            $fields['x-' . $name] = self::native($value);
        }

        return $fields;
    }

    /**
     * @return null|array<array-key, mixed>|bool|float|int|stdClass|string
     */
    private static function native(
        AbstractValues|bool|float|int|string|null $value,
    ): array|bool|float|int|stdClass|string|null {
        if (!$value instanceof AbstractValues) {
            return $value;
        }

        $result = [];

        foreach ($value->items as $name => $item) {
            $result[$name] = $item instanceof AbstractValues ? self::native($item) : $item;
        }

        return $value instanceof OpenapiObject ? (object) $result : array_values($result);
    }
}
