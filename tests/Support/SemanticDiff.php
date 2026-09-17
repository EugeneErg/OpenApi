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
 * Смысловое сравнение исходного документа с тем, что пакет из него собрал.
 *
 * Пакет не обязан воспроизводить текст дословно: значение, которое ни на что
 * не влияет, он вправе опустить или записать другой, эквивалентной формой.
 * Все такие допущения перечислены здесь, в одном месте, — всё прочее считается потерей.
 */
final class SemanticDiff
{
    /** @var list<string> */
    public array $differences = [];

    /** Мест, где пакет выбрал равнозначную запись вместо исходной. */
    public int $rewritten = 0;

    private bool $v31;

    public function __construct(stdClass $original, stdClass $built)
    {
        $version = $original->openapi ?? '';
        $this->v31 = is_string($version) && str_starts_with($version, '3.1.');
        $this->compare($this->normalize($original), $this->normalize($built), '#');
    }

    /**
     * Приводит эквивалентные формы к одной.
     */
    private function normalize(mixed $value, string $key = ''): mixed
    {
        if ($value instanceof stdClass) {
            $vars = get_object_vars($value);

            // allOf из единственной схемы — это сама схема
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
            // JSON не различает 1 и 1.0
            return is_float($value) && floor($value) === $value && abs($value) < PHP_INT_MAX ? (int) $value : $value;
        }

        $value = array_map(fn (mixed $item): mixed => $this->normalize($item), $value);

        // порядок не несёт смысла: параметры различаются по in+name, required и enum — множества
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
            // перечень — это набор допустимых значений: JSON Schema лишь советует
            // ему быть без повторов, а повтор ничего не добавляет
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
     * Пакет выбрал равнозначную запись вместо исходной.
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

        // перечисление рядом с применителем раскладывается на allOf
        return $leftAllOf === 0
            || array_key_exists('const', $left)
            || array_key_exists('enum', $left);
    }

    /**
     * Все значения-листья поддерева: имя ключа → набор значений в виде JSON.
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
     * Приводит к одной форме записи, равнозначные по JSON Schema. Пакет выбирает
     * из них одну, и обе стороны сравнения должны выглядеть одинаково.
     *
     * @param array<array-key, mixed> $vars
     *
     * @return array<array-key, mixed>
     */
    private function sameMeaning(array $vars): array
    {
        // порядок типов ничего не значит, а массив из одного типа — это сам тип
        if (is_array($vars['type'] ?? null)) {
            $types = array_values(array_unique(array_filter($vars['type'], is_string(...))));
            sort($types);
            $vars['type'] = count($types) === 1 ? $types[0] : $types;
        }

        // nullable дописывает null в сам перечень: по 3.0.3 иначе null недопустим
        $nullable = ($vars['nullable'] ?? null) === true
            || (is_array($vars['type'] ?? null) && in_array('null', $vars['type'], true));

        if ($nullable && is_array($vars['enum'] ?? null) && $vars['enum'] !== []
            && !in_array(null, $vars['enum'], true)) {
            $vars['enum'] = [...$vars['enum'], null];
        }

        // `const: x` — это то же, что `enum: [x]`
        if (!array_key_exists('const', $vars) && ($vars['enum'] ?? null) !== null
            && is_array($vars['enum']) && count($vars['enum']) === 1) {
            $vars['const'] = array_values($vars['enum'])[0];
            unset($vars['enum']);
        }

        // пустому перечню не подходит ничего — как и `not: {}`
        if (($vars['enum'] ?? null) === []) {
            unset($vars['enum']);
            $vars['not'] = new stdClass();
        }

        // в 3.1 `$ref` — обычное ключевое слово, и соседи применяются вместе с ним;
        // в 3.0 они не действуют, и пакет их отбрасывает
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

        // YAML не отличает пустую карту от пустого списка
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

        // Часть записей пакет раскладывает на равнозначные: объединение типов —
        // на `anyOf` из схем этих типов, перечисление рядом с применителем —
        // на `allOf`. Повторять эту раскладку здесь нельзя: проверка сверяла бы
        // код сам с собой. Поэтому у таких мест сверяется более слабое, зато
        // независимое условие — ни одно значение не потерялось.
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
     * Значение исходного документа в той форме, в которой его запишет пакет.
     */
    private function equivalent(stdClass $owner, string $name, mixed $value): mixed
    {
        // имя HTTP-схемы регистронезависимо
        if ($name === 'scheme' && ($owner->type ?? null) === 'http' && is_string($value)) {
            $lower = strtolower($value);

            return in_array($lower, ['basic', 'bearer'], true) ? $lower : $value;
        }

        return $value;
    }

    /**
     * Можно ли не выводить ключ, не изменив смысла.
     */
    private function mayDrop(stdClass $owner, string $name, mixed $value, string $pointer): bool
    {
        // пустые карты и списки ничего не объявляют
        if (($value instanceof stdClass && get_object_vars($value) === []) || $value === []) {
            return true;
        }

        // Encoding Object действует только для форм и multipart
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

        // style и explode, равные умолчанию для своего in
        if (isset($owner->in) && ($name === 'style' || $name === 'explode')) {
            $style = $owner->style ?? (in_array($owner->in, ['query', 'cookie'], true) ? 'form' : 'simple');

            return $name === 'style'
                ? $value === (in_array($owner->in, ['query', 'cookie'], true) ? 'form' : 'simple')
                : $value === ($style === 'form');
        }

        // рядом с перечислением примеры и проверки ничего не добавляют (README, «Перечисления»)
        if (isset($owner->enum) || property_exists($owner, 'const')) {
            return in_array($name, [
                'example', 'examples', 'minLength', 'maxLength', 'pattern', 'minimum', 'maximum',
                'minItems', 'maxItems',
            ], true);
        }

        // в 3.0 соседи $ref не действуют
        if (!$this->v31 && isset($owner->{'$ref'})) {
            return true;
        }

        // пример, не подходящий под тип схемы, ничего не иллюстрирует
        if ($name === 'example' && is_string($owner->type ?? null) && !self::fits($owner->type, $value)) {
            return true;
        }

        // name и in объявлены только для apiKey: таблица «Applies To» в спецификации
        if (in_array($name, ['name', 'in'], true)
            && in_array($owner->type ?? null, ['http', 'oauth2', 'openIdConnect'], true)) {
            return true;
        }

        // Проверка, применимая к значениям другого типа, для этой схемы ничего
        // не значит: `uniqueItems` у строки всегда выполнено, потому что строка
        // не массив. Объявленный тип делает такое слово мёртвым.
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
     * Можно ли вывести ключ, которого не было, не изменив смысла.
     */
    private function mayAdd(stdClass $owner, string $name, mixed $value): bool
    {
        // тип, выведенный из значений перечисления
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
