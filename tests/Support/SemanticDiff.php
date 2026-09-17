<?php

declare(strict_types = 1);

namespace Tests\Support;

use stdClass;

use function array_key_exists;
use function count;
use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function sprintf;
use function strlen;

/**
 * A comparison by meaning of the original document with what the package built from it.
 *
 * The package is not obliged to reproduce the text word for word: a value that affects
 * nothing it may omit or write in another, equivalent form. Every such allowance is
 * listed here, in one place — everything else counts as a loss.
 */
final class SemanticDiff
{
    /** @var list<string> */
    public array $differences = [];

    /** The number of places where the package chose an equivalent form over the original. */
    public int $rewritten = 0;

    private bool $v31;

    public function __construct(stdClass $original, stdClass $built)
    {
        $version = $original->openapi ?? '';
        $this->v31 = is_string($version) && str_starts_with($version, '3.1.');
        $this->compare($this->normalize($original), $this->normalize($built), '#');
    }

    /**
     * Brings the equivalent forms to one.
     */
    private function normalize(mixed $value, string $key = ''): mixed
    {
        if ($value instanceof stdClass) {
            $vars = get_object_vars($value);

            // an allOf of a single schema is that schema itself
            $allOf = $vars['allOf'] ?? null;

            if (array_keys($vars) === ['allOf'] && is_array($allOf) && count($allOf) === 1) {
                return $this->normalize(array_values($allOf)[0], $key);
            }

            $vars = $this->sameMeaning($vars);
            $result = new stdClass();

            foreach ($vars as $name => $item) {
                $result->{$name} = $this->normalize($item, (string) $name);
            }

            return $result;
        }

        if (!is_array($value)) {
            // JSON does not tell 1 from 1.0
            return is_float($value) && floor($value) === $value && abs($value) < PHP_INT_MAX ? (int) $value : $value;
        }

        $value = array_map(fn (mixed $item): mixed => $this->normalize($item), $value);

        // the order carries no meaning: parameters differ by in+name, and required and enum are sets
        if ($key === 'parameters' && array_is_list($value)) {
            $map = new stdClass();

            foreach ($value as $parameter) {
                $id = $parameter instanceof stdClass && isset($parameter->name)
                    ? json_encode([$parameter->in ?? null, $parameter->name])
                    : 'ref:' . json_encode($parameter);
                $map->{$id} = $parameter;
            }

            return $map;
        }

        if (($key === 'required' || $key === 'enum') && array_is_list($value)) {
            // an enumeration is a set of admissible values: JSON Schema merely advises
            // it to be free of repeats, and a repeat adds nothing
            if ($key === 'enum') {
                $value = array_values(array_intersect_key(
                    $value,
                    array_unique(array_map(static fn (mixed $item): string => (string) json_encode($item), $value)),
                ));
            }

            usort($value, static fn (mixed $a, mixed $b): int => strcmp((string) json_encode($a), (string) json_encode($b)));
        }

        return $value;
    }

    /**
     * The package chose an equivalent form over the original.
     *
     * @param array<array-key, mixed> $left
     * @param array<array-key, mixed> $right
     */
    private function wasRewritten(array $left, array $right): bool
    {
        $types = $left['type'] ?? null;

        if (is_array($types) && count(array_filter($types, static fn (mixed $t): bool => $t !== 'null')) > 1) {
            return true;
        }

        $leftAllOf = is_array($left['allOf'] ?? null) ? count($left['allOf']) : 0;
        $rightAllOf = is_array($right['allOf'] ?? null) ? count($right['allOf']) : 0;

        if ($rightAllOf <= $leftAllOf) {
            return false;
        }

        // an enumeration beside an applicator is laid out as an allOf
        return $leftAllOf === 0
            || array_key_exists('const', $left)
            || array_key_exists('enum', $left);
    }

    /**
     * Every leaf value of a subtree: the key's name → the set of values as JSON.
     *
     * @return array<string, list<string>>
     */
    private static function leaves(mixed $value, string $name = ''): array
    {
        if ($value instanceof stdClass) {
            $result = [];

            foreach (get_object_vars($value) as $key => $item) {
                foreach (self::leaves($item, (string) $key) as $leaf => $values) {
                    $result[$leaf] = [...$result[$leaf] ?? [], ...$values];
                }
            }

            return $result;
        }

        if (is_array($value)) {
            $result = [];

            foreach ($value as $item) {
                foreach (self::leaves($item, $name) as $leaf => $values) {
                    $result[$leaf] = [...$result[$leaf] ?? [], ...$values];
                }
            }

            return $result;
        }

        return [$name => [(string) json_encode($value)]];
    }

    /**
     * Brings the forms that JSON Schema treats as equal to one. The package picks one of
     * them, and both sides of the comparison have to look alike.
     *
     * @param array<array-key, mixed> $vars
     *
     * @return array<array-key, mixed>
     */
    private function sameMeaning(array $vars): array
    {
        // the order of the types means nothing, and an array of one type is that type
        if (is_array($vars['type'] ?? null)) {
            $types = array_values(array_unique(array_filter($vars['type'], is_string(...))));
            sort($types);
            $vars['type'] = count($types) === 1 ? $types[0] : $types;
        }

        // nullable adds null to the enumeration itself: by 3.0.3 null is inadmissible otherwise
        $nullable = ($vars['nullable'] ?? null) === true
            || (is_array($vars['type'] ?? null) && in_array('null', $vars['type'], true));

        if ($nullable && is_array($vars['enum'] ?? null) && $vars['enum'] !== []
            && !in_array(null, $vars['enum'], true)) {
            $vars['enum'] = [...$vars['enum'], null];
        }

        // `const: x` is the same as `enum: [x]`
        if (!array_key_exists('const', $vars) && ($vars['enum'] ?? null) !== null
            && is_array($vars['enum']) && count($vars['enum']) === 1) {
            $vars['const'] = array_values($vars['enum'])[0];
            unset($vars['enum']);
        }

        // nothing matches an empty enumeration — just as with `not: {}`
        if (($vars['enum'] ?? null) === []) {
            unset($vars['enum']);
            $vars['not'] = new stdClass();
        }

        // in 3.1 `$ref` is an ordinary keyword and its siblings apply along with it;
        // in 3.0 they do not apply, and the package drops them
        if ($this->v31 && array_key_exists('$ref', $vars) && count($vars) > 1) {
            $ref = new stdClass();
            $ref->{'$ref'} = $vars['$ref'];
            $allOf = $vars['allOf'] ?? [];
            $vars['allOf'] = [$ref, ...(is_array($allOf) ? $allOf : [$allOf])];
            unset($vars['$ref']);
        }

        return $vars;
    }

    private function compare(mixed $original, mixed $built, string $pointer): void
    {
        if ($original instanceof stdClass && ($built instanceof stdClass || $built === [])) {
            $built = $built instanceof stdClass ? $built : new stdClass();
            $this->compareObjects($original, $built, $pointer);

            return;
        }

        // YAML does not tell an empty map from an empty list
        if ($original === [] && $built instanceof stdClass && get_object_vars($built) === []) {
            return;
        }

        if (is_array($original) && is_array($built)) {
            if (count($original) !== count($built)) {
                $this->differences[] = sprintf('%s: %d items became %d', $pointer, count($original), count($built));

                return;
            }

            $built = array_values($built);

            foreach (array_values($original) as $index => $item) {
                $this->compare($item, $built[$index] ?? null, $pointer . '/' . $index);
            }

            return;
        }

        if ($original !== $built) {
            $this->differences[] = sprintf(
                '%s: %s became %s',
                $pointer,
                self::show($original),
                self::show($built),
            );
        }
    }

    private function compareObjects(stdClass $original, stdClass $built, string $pointer): void
    {
        $left = get_object_vars($original);
        $right = get_object_vars($built);

        // Some forms the package lays out as equivalents: a union of types becomes an
        // `anyOf` of the schemas of those types, an enumeration beside an applicator
        // becomes an `allOf`. Repeating that layout here is out of the question: the
        // check would compare the code with itself. So for such places a weaker but
        // independent condition is checked — that not a single value was lost.
        if ($this->wasRewritten($left, $right)) {
            ++$this->rewritten;

            foreach (self::leaves($original) as $name => $values) {
                foreach (array_diff($values, self::leaves($built)[$name] ?? []) as $value) {
                    if (!$this->mayDrop($original, $name, json_decode($value), $pointer)) {
                        $this->differences[] = sprintf('%s: lost %s after a rewrite', $pointer . '/' . $name, $value);
                    }
                }
            }

            return;
        }

        foreach ($left as $name => $value) {
            $name = (string) $name;
            $child = $pointer . '/' . str_replace(['~', '/'], ['~0', '~1'], $name);

            if (array_key_exists($name, $right)) {
                $this->compare($this->equivalent($original, $name, $value), $right[$name], $child);
            } elseif (!$this->mayDrop($original, $name, $value, $pointer)) {
                $this->differences[] = sprintf('%s: lost %s', $child, self::show($value));
            }
        }

        foreach ($right as $name => $value) {
            $name = (string) $name;

            if (!array_key_exists($name, $left) && !$this->mayAdd($original, $name, $value)) {
                $this->differences[] = sprintf(
                    '%s/%s: added %s',
                    $pointer,
                    str_replace(['~', '/'], ['~0', '~1'], $name),
                    self::show($value),
                );
            }
        }
    }

    /**
     * A value of the original document in the form the package will write it in.
     */
    private function equivalent(stdClass $owner, string $name, mixed $value): mixed
    {
        // an HTTP scheme's name is case-insensitive
        if ($name === 'scheme' && ($owner->type ?? null) === 'http' && is_string($value)) {
            $lower = strtolower($value);

            return in_array($lower, ['basic', 'bearer'], true) ? $lower : $value;
        }

        return $value;
    }

    /**
     * Whether the key may be left unprinted without changing the meaning.
     */
    private function mayDrop(stdClass $owner, string $name, mixed $value, string $pointer): bool
    {
        // empty maps and lists declare nothing
        if (($value instanceof stdClass && get_object_vars($value) === []) || $value === []) {
            return true;
        }

        // an Encoding Object applies to forms and multipart only
        if ($name === 'encoding' && preg_match('{/content/([^/]+)$}', $pointer, $match) === 1) {
            $type = strtolower(str_replace(['~1', '~0'], ['/', '~'], $match[1]));

            return $type !== 'application/x-www-form-urlencoded' && !str_starts_with($type, 'multipart/');
        }

        $defaults = [
            'required' => false, 'deprecated' => false, 'allowEmptyValue' => false, 'allowReserved' => false,
            'nullable' => false, 'readOnly' => false, 'writeOnly' => false, 'uniqueItems' => false,
            'minLength' => 0, 'minItems' => 0, 'minProperties' => 0, 'additionalProperties' => true,
        ];

        if (array_key_exists($name, $defaults) && $defaults[$name] === $value) {
            return true;
        }

        // style and explode that equal the default for their in
        if (isset($owner->in) && ($name === 'style' || $name === 'explode')) {
            $style = $owner->style ?? (in_array($owner->in, ['query', 'cookie'], true) ? 'form' : 'simple');

            return $name === 'style'
                ? $value === (in_array($owner->in, ['query', 'cookie'], true) ? 'form' : 'simple')
                : $value === ($style === 'form');
        }

        // beside an enumeration the examples and the checks add nothing (README, "Enumerations")
        if (isset($owner->enum) || property_exists($owner, 'const')) {
            return in_array($name, [
                'example', 'examples', 'minLength', 'maxLength', 'pattern', 'minimum', 'maximum',
                'minItems', 'maxItems',
            ], true);
        }

        // in 3.0 the siblings of a $ref do not apply
        if (!$this->v31 && isset($owner->{'$ref'})) {
            return true;
        }

        // an example that does not fit the schema's type illustrates nothing
        if ($name === 'example' && is_string($owner->type ?? null) && !self::fits($owner->type, $value)) {
            return true;
        }

        // name and in are declared for apiKey only: the "Applies To" table in the specification
        if (in_array($name, ['name', 'in'], true)
            && in_array($owner->type ?? null, ['http', 'oauth2', 'openIdConnect'], true)) {
            return true;
        }

        // A check that applies to values of another type means nothing for this schema:
        // `uniqueItems` on a string always holds, because a string is not an array. A
        // declared type makes such a keyword dead.
        $kinds = [
            'minLength' => 'string', 'maxLength' => 'string', 'pattern' => 'string',
            'minimum' => 'number', 'maximum' => 'number', 'multipleOf' => 'number',
            'exclusiveMinimum' => 'number', 'exclusiveMaximum' => 'number',
            'minItems' => 'array', 'maxItems' => 'array', 'uniqueItems' => 'array',
            'items' => 'array', 'prefixItems' => 'array', 'contains' => 'array',
            'minProperties' => 'object', 'maxProperties' => 'object', 'required' => 'object',
            'properties' => 'object', 'patternProperties' => 'object', 'propertyNames' => 'object',
        ];

        if (isset($kinds[$name]) && is_string($owner->type ?? null)) {
            return $kinds[$name] !== ($owner->type === 'integer' ? 'number' : $owner->type);
        }

        return false;
    }

    /**
     * Whether a key that was not there may be printed without changing the meaning.
     */
    private function mayAdd(stdClass $owner, string $name, mixed $value): bool
    {
        // a type inferred from the values of an enumeration
        if ($name === 'type' && (isset($owner->enum) || property_exists($owner, 'const'))) {
            return true;
        }

        return false;
    }

    private static function fits(string $type, mixed $value): bool
    {
        return match ($type) {
            'boolean' => is_bool($value),
            'string' => is_string($value),
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'array' => is_array($value),
            'object' => $value instanceof stdClass,
            default => true,
        } || $value === null;
    }

    private static function show(mixed $value): string
    {
        $json = (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return strlen($json) > 120 ? substr($json, 0, 120) . '…' : $json;
    }
}
