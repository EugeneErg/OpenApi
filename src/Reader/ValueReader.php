<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Reader;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValues;
use EugeneErg\OpenApi\Components\Schemas\Array\OpenapiArray;
use EugeneErg\OpenApi\Components\Schemas\Array\Value as ArrayValue;
use EugeneErg\OpenApi\Components\Schemas\Boolean\Value as BooleanValue;
use EugeneErg\OpenApi\Components\Schemas\Integer\Value as IntegerValue;
use EugeneErg\OpenApi\Components\Schemas\Number\Value as NumberValue;
use EugeneErg\OpenApi\Components\Schemas\Object\OpenapiObject;
use EugeneErg\OpenApi\Components\Schemas\Object\Value as ObjectValue;
use EugeneErg\OpenApi\Components\Schemas\String\Value as StringValue;
use EugeneErg\OpenApi\Components\Schemas\Untyped\Value as UntypedValue;
use stdClass;

use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_scalar;
use function is_string;

/**
 * Reads values: `default`, `example`, `examples` and the members of an enum.
 *
 * A value is not a schema: it has no keywords, but it has a type, and the type decides
 * the wrapper class. So it is read apart from the Schema Object — and read the same way
 * for a schema, for an enum and for an Example Object.
 */
final readonly class ValueReader
{
    public function readValues(Node $node): AbstractValues
    {
        if (is_array($node->value)) {
            return new OpenapiArray(...array_map($this->nativeOf(...), $node->list()));
        }

        $items = [];

        foreach ($node->map() as $key => $item) {
            $items[$key] = $this->nativeOf($item);
        }

        return OpenapiObject::fromArray($items);
    }

    /**
     * An example that does not fit the schema's type affects nothing: it only illustrates,
     * and an illustration like that has no place in the document. A node without a value
     * means there is no example.
     */
    public function example(Node $schema, string $type): Node
    {
        $example = $schema->get('example');

        if (!$example->isPresent() || $example->value === null) {
            return $example;
        }

        $value = $example->value;
        $fits = match ($type) {
            'boolean' => is_bool($value),
            'string' => is_string($value),
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'array' => is_array($value),
            default => $value instanceof stdClass || $value === [],
        };

        return $fits ? $example : new Node(null, $example->path, present: false);
    }

    public function untypedValue(Node $node): ?UntypedValue
    {
        return $node->isPresent() ? new UntypedValue($this->nativeOf($node)) : null;
    }

    public function booleanValue(Node $node): ?BooleanValue
    {
        return $node->isPresent() ? new BooleanValue($node->value === null ? null : $node->bool()) : null;
    }

    public function integerValue(Node $node): ?IntegerValue
    {
        return $node->isPresent() ? new IntegerValue($node->value === null ? null : $node->int()) : null;
    }

    public function numberValue(Node $node): ?NumberValue
    {
        return $node->isPresent() ? new NumberValue($node->value === null ? null : $node->floatOrNull()) : null;
    }

    public function arrayValue(Node $node): ?ArrayValue
    {
        if (!$node->isPresent()) {
            return null;
        }

        if ($node->value === null) {
            return new ArrayValue(null);
        }

        $values = $this->readValues($node);

        return new ArrayValue($values instanceof OpenapiArray ? $values : throw $node->unexpected('a list'));
    }

    public function objectValue(Node $node): ?ObjectValue
    {
        if (!$node->isPresent()) {
            return null;
        }

        if ($node->value === null) {
            return new ObjectValue(null);
        }

        // through ext-yaml an empty map is indistinguishable from an empty list,
        // and the schema expects a map in this place
        if ($node->value === []) {
            return new ObjectValue(new OpenapiObject());
        }

        $values = $this->readValues($node);

        return new ObjectValue($values instanceof OpenapiObject ? $values : throw $node->unexpected('a mapping'));
    }

    public function stringValue(Node $node): ?StringValue
    {
        return $node->isPresent() ? new StringValue($node->value === null ? null : $node->string()) : null;
    }

    public function listOf(Node $node): OpenapiArray
    {
        $values = $this->readValues($node);

        return $values instanceof OpenapiArray ? $values : throw $node->unexpected('a list');
    }

    public function mapOf(Node $node): OpenapiObject
    {
        $values = $this->readValues($node);

        return $values instanceof OpenapiObject ? $values : throw $node->unexpected('a mapping');
    }

    public static function integer(Node $node): int
    {
        return is_int($node->value) ? $node->value : (int) ($node->floatOrNull() ?? throw $node->unexpected('an integer'));
    }

    public static function number(Node $node): float|int
    {
        return $node->numberOrNull() ?? throw $node->unexpected('a number');
    }

    public function nativeOf(Node $node): AbstractValues|bool|float|int|string|null
    {
        $value = $node->value;

        if (is_array($value) || $value instanceof stdClass) {
            return $this->readValues($node);
        }

        return is_scalar($value) || $value === null
            ? $value
            : throw $node->unexpected('a scalar or a structure');
    }
}
