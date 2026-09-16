<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Reader;

use BackedEnum;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractEnumSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValue;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValues;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Access;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Discriminator;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Vocabularies;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Xml;
use EugeneErg\OpenApi\Components\Schemas\Array\OpenapiArray;
use EugeneErg\OpenApi\Components\Schemas\Array\Schema as ArraySchema;
use EugeneErg\OpenApi\Components\Schemas\Array\Value as ArrayValue;
use EugeneErg\OpenApi\Components\Schemas\Boolean\Schema as BooleanSchema;
use EugeneErg\OpenApi\Components\Schemas\Boolean\Value as BooleanValue;
use EugeneErg\OpenApi\Components\Schemas\Integer\EnumSchema as IntegerEnumSchema;
use EugeneErg\OpenApi\Components\Schemas\Integer\Format as IntegerFormat;
use EugeneErg\OpenApi\Components\Schemas\Integer\Integers;
use EugeneErg\OpenApi\Components\Schemas\Integer\Schema as IntegerSchema;
use EugeneErg\OpenApi\Components\Schemas\Integer\Value as IntegerValue;
use EugeneErg\OpenApi\Components\Schemas\Number\EnumSchema as NumberEnumSchema;
use EugeneErg\OpenApi\Components\Schemas\Number\Format as NumberFormat;
use EugeneErg\OpenApi\Components\Schemas\Number\Numbers;
use EugeneErg\OpenApi\Components\Schemas\Number\Schema as NumberSchema;
use EugeneErg\OpenApi\Components\Schemas\Number\Value as NumberValue;
use EugeneErg\OpenApi\Components\Schemas\Object\DependentRequired;
use EugeneErg\OpenApi\Components\Schemas\Object\OpenapiObject;
use EugeneErg\OpenApi\Components\Schemas\Object\PatternProperties;
use EugeneErg\OpenApi\Components\Schemas\Object\Properties;
use EugeneErg\OpenApi\Components\Schemas\Object\Property;
use EugeneErg\OpenApi\Components\Schemas\Object\Schema as ObjectSchema;
use EugeneErg\OpenApi\Components\Schemas\Object\Value as ObjectValue;
use EugeneErg\OpenApi\Components\Schemas\String\EnumSchema as StringEnumSchema;
use EugeneErg\OpenApi\Components\Schemas\String\Format as StringFormat;
use EugeneErg\OpenApi\Components\Schemas\String\Schema as StringSchema;
use EugeneErg\OpenApi\Components\Schemas\String\Strings;
use EugeneErg\OpenApi\Components\Schemas\String\Value as StringValue;
use EugeneErg\OpenApi\Components\Schemas\Untyped\Schema as UntypedSchema;
use EugeneErg\OpenApi\Components\Schemas\Untyped\Schemas as UntypedSchemas;
use EugeneErg\OpenApi\Components\Schemas\Untyped\Value as UntypedValue;
use EugeneErg\OpenApi\ExternalDocs;
use stdClass;

use function count;
use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_scalar;
use function is_string;
use function sprintf;

/**
 * Разбор Schema Object.
 *
 * Модель пакета типоцентрична, поэтому ветка выбирается по `type`. Отсутствие типа
 * или тип-массив из 3.1 читаются в Untyped\Schema, `["string", "null"]` —
 * в строковую схему с nullable.
 */
final readonly class SchemaReader
{
    public function __construct(private Registry $registry)
    {
    }

    /**
     * Схема по узлу. Если узел объявлен в реестре, возвращается общий экземпляр:
     * иначе схема внутри $defs строилась бы дважды и ссылка на неё не нашла бы цель.
     */
    public function read(Node $node): AbstractSchema
    {
        $pointer = $this->registry->pointerOfNode($node);

        if ($pointer !== null && $this->registry->has($pointer)) {
            return $this->registry->resolveSchema($pointer);
        }

        return $this->readSchema($node);
    }

    public function readSchema(Node $node): AbstractSchema
    {
        if ($node->has('$ref')) {
            return $this->registry->resolveSchema(
                $this->registry->pointerOf($node->get('$ref')->string(), $node),
            );
        }

        [$type, $nullable] = $this->readType($node);
        $common = $this->common($node, $nullable);

        if ($node->has('enum')) {
            return $this->readEnum($node, $type, [
                'title' => $node->get('title')->stringOrNull(),
                'description' => $node->get('description')->stringOrNull(),
                'nullable' => $nullable,
                'deprecated' => $node->get('deprecated')->boolOr(false),
            ]);
        }

        // type может отсутствовать, а форма — быть описана: тогда выбираем ветку
        // по ключевым словам и не объявляем type в выводе
        $declareType = $type !== null;
        $type ??= $this->inferShape($node);

        return match ($type) {
            'string' => new StringSchema(...[...$common, ...$this->stringOnly($node)]),
            'integer' => new IntegerSchema(...[...$common, ...$this->integerOnly($node)]),
            'number' => new NumberSchema(...[...$common, ...$this->numberOnly($node)]),
            'boolean' => new BooleanSchema(...[...$common, ...$this->booleanOnly($node)]),
            'array' => new ArraySchema(...[...$common, ...$this->arrayOnly($node), 'declareType' => $declareType]),
            'object' => new ObjectSchema(...[...$common, ...$this->objectOnly($node), 'declareType' => $declareType]),
            default => new UntypedSchema(...$common),
        };
    }

    /**
     * Значение в типизированной схеме должно быть того же типа, поэтому класс
     * обёртки выбирается по type схемы.
     */
    public function readValue(Node $node, ?string $type = null): ?AbstractValue
    {
        if ($node->isMissing()) {
            return null;
        }

        $value = $node->value;
        $nested = is_array($value) || $value instanceof stdClass ? $this->readValues($node) : null;

        return match ($type) {
            'string' => new StringValue($node->string()),
            'integer' => new IntegerValue($node->int()),
            'number' => new NumberValue($node->floatOrNull()),
            'boolean' => new BooleanValue($node->bool()),
            'array' => new ArrayValue($nested instanceof OpenapiArray ? $nested : throw $node->unexpected('a list')),
            'object' => new ObjectValue($nested instanceof OpenapiObject ? $nested : throw $node->unexpected('a mapping')),
            default => new UntypedValue($nested ?? (
                is_scalar($value) || $value === null
                    ? $value
                    : throw $node->unexpected('a scalar or a structure')
            )),
        };
    }

    public function readValues(Node $node): AbstractValues
    {
        if (is_array($node->value)) {
            return new OpenapiArray(...array_map($this->nativeOf(...), $node->list()));
        }

        $items = [];

        foreach ($node->map() as $key => $item) {
            $items[$key] = $this->nativeOf($item);
        }

        return new OpenapiObject(...$items);
    }

    /**
     * Ветка для схемы без type: она определяется по ключевым словам,
     * применимым только к объекту или только к массиву.
     */
    private function inferShape(Node $node): ?string
    {
        $object = ['properties', 'required', 'patternProperties', 'propertyNames', 'additionalProperties',
            'dependentRequired', 'dependentSchemas', 'minProperties', 'maxProperties'];
        $array = ['items', 'prefixItems', 'contains', 'minItems', 'maxItems', 'uniqueItems'];

        foreach ($object as $keyword) {
            if ($node->has($keyword)) {
                return 'object';
            }
        }

        foreach ($array as $keyword) {
            if ($node->has($keyword)) {
                return 'array';
            }
        }

        return null;
    }

    /**
     * @return array{null|string, bool}
     */
    private function readType(Node $node): array
    {
        $type = $node->get('type');

        if ($type->isMissing()) {
            return [null, $node->get('nullable')->boolOr(false)];
        }

        if (is_string($type->value)) {
            return [$type->value, $node->get('nullable')->boolOr(false)];
        }

        // 3.1: type задаётся массивом, и null там — способ выразить nullable
        $types = $type->strings();
        $nullable = in_array('null', $types, true);
        $rest = array_values(array_filter($types, static fn (string $item): bool => $item !== 'null'));

        if (count($rest) > 1) {
            throw $type->unexpected('a single type besides null: the package models schemas by type');
        }

        return [$rest[0] ?? null, $nullable];
    }

    /**
     * Поля, общие для всех схем.
     *
     * Форма массива описана точно, иначе распаковка в конструктор непроверяема:
     * PHPStan увидел бы mixed на каждом параметре.
     *
     * @return array{
     *     title: ?string,
     *     description: ?string,
     *     nullable: bool,
     *     access: ?Access,
     *     deprecated: bool,
     *     externalDocs: ?ExternalDocs,
     *     xml: ?Xml,
     *     anyOf: ?UntypedSchemas,
     *     allOf: ?UntypedSchemas,
     *     oneOf: ?UntypedSchemas,
     *     not: ?AbstractSchema,
     *     discriminator: ?Discriminator,
     *     examples: ?AbstractValues,
     *     comment: ?string,
     *     defs: ?UntypedSchemas,
     *     id: ?string,
     *     anchor: ?string,
     *     dynamicAnchor: ?string,
     *     dynamicRef: ?AbstractSchema,
     *     vocabulary: ?Vocabularies,
     *     if: ?AbstractSchema,
     *     then: ?AbstractSchema,
     *     else: ?AbstractSchema
     * }
     */
    private function common(Node $node, bool $nullable): array
    {
        return [
            'title' => $node->get('title')->stringOrNull(),
            'description' => $node->get('description')->stringOrNull(),
            'nullable' => $nullable,
            'access' => $this->readAccess($node),
            'deprecated' => $node->get('deprecated')->boolOr(false),
            'externalDocs' => $this->readExternalDocs($node->get('externalDocs')),
            'xml' => $this->readXml($node->get('xml')),
            'anyOf' => $this->readSchemas($node->get('anyOf')),
            'allOf' => $this->readSchemas($node->get('allOf')),
            'oneOf' => $this->readSchemas($node->get('oneOf')),
            'not' => $node->has('not') ? $this->read($node->get('not')) : null,
            'discriminator' => $this->readDiscriminator($node->get('discriminator')),
            'examples' => $node->has('examples') ? $this->readValues($node->get('examples')) : null,
            'comment' => $node->get('$comment')->stringOrNull(),
            'defs' => $this->readSchemas($node->get('$defs')),
            'id' => $node->get('$id')->stringOrNull(),
            'anchor' => $node->get('$anchor')->stringOrNull(),
            'dynamicAnchor' => $node->get('$dynamicAnchor')->stringOrNull(),
            'dynamicRef' => $this->readDynamicRef($node),
            'vocabulary' => $this->readVocabulary($node->get('$vocabulary')),
            'if' => $node->has('if') ? $this->read($node->get('if')) : null,
            'then' => $node->has('then') ? $this->read($node->get('then')) : null,
            'else' => $node->has('else') ? $this->read($node->get('else')) : null,
        ];
    }

    /**
     * @return array{default: ?BooleanValue, example: ?BooleanValue, const: ?BooleanValue}
     */
    private function booleanOnly(Node $node): array
    {
        return [
            'default' => $this->booleanValue($node->get('default')),
            'example' => $this->booleanValue($node->get('example')),
            'const' => $this->booleanValue($node->get('const')),
        ];
    }

    private function booleanValue(Node $node): ?BooleanValue
    {
        return $node->isMissing() ? null : new BooleanValue($node->bool());
    }

    private function integerValue(Node $node): ?IntegerValue
    {
        return $node->isMissing() ? null : new IntegerValue($node->int());
    }

    private function numberValue(Node $node): ?NumberValue
    {
        return $node->isMissing() ? null : new NumberValue($node->floatOrNull());
    }

    private function arrayValue(Node $node): ?ArrayValue
    {
        if ($node->isMissing()) {
            return null;
        }

        $values = $this->readValues($node);

        return new ArrayValue($values instanceof OpenapiArray ? $values : throw $node->unexpected('a list'));
    }

    private function objectValue(Node $node): ?ObjectValue
    {
        if ($node->isMissing()) {
            return null;
        }

        $values = $this->readValues($node);

        return new ObjectValue($values instanceof OpenapiObject ? $values : throw $node->unexpected('a mapping'));
    }

    /**
     * @param array{title: ?string, description: ?string, nullable: bool, deprecated: bool} $common
     */
    private function readEnum(Node $node, ?string $type, array $common): AbstractEnumSchema
    {
        $values = $node->get('enum');

        // Untyped-варианта enum в пакете нет: тип выводится из значений,
        // как это делает и сама AbstractEnumSchema при сериализации.
        $type ??= $this->inferEnumType($values);

        return match ($type) {
            'integer' => new IntegerEnumSchema(
                enums: new Integers(...array_map(static fn (Node $i): int => $i->int(), $values->list())),
                title: $common['title'],
                description: $common['description'],
                nullable: $common['nullable'],
                deprecated: $common['deprecated'],
                default: $this->integerValue($node->get('default')),
            ),
            'number' => new NumberEnumSchema(
                enums: new Numbers(...array_map(
                    static fn (Node $i): float => $i->floatOrNull() ?? throw $i->unexpected('a number'),
                    $values->list(),
                )),
                title: $common['title'],
                description: $common['description'],
                nullable: $common['nullable'],
                deprecated: $common['deprecated'],
                default: $this->numberValue($node->get('default')),
            ),
            default => new StringEnumSchema(
                enums: new Strings(...array_map(static fn (Node $i): string => $i->string(), $values->list())),
                title: $common['title'],
                description: $common['description'],
                nullable: $common['nullable'],
                deprecated: $common['deprecated'],
                default: $this->stringValue($node->get('default')),
            ),
        };
    }

    private function inferEnumType(Node $values): string
    {
        $first = $values->list()[0] ?? throw $values->unexpected('a non-empty enum');

        return match (true) {
            is_int($first->value) => 'integer',
            is_float($first->value) => 'number',
            default => 'string',
        };
    }

    /**
     * @return null|int<0, max>
     */
    private function nonNegative(Node $node): ?int
    {
        $value = $node->intOrNull();

        if ($value === null) {
            return null;
        }

        return $value >= 0 ? $value : throw $node->unexpected('a non-negative integer');
    }

    /**
     * @return array{
     *     format: null|string|StringFormat,
     *     minLength: int<0, max>,
     *     maxLength: null|int<0, max>,
     *     pattern: ?string,
     *     contentEncoding: ?string,
     *     contentMediaType: ?string,
     *     contentSchema: ?AbstractSchema,
     *     default: ?StringValue,
     *     example: ?StringValue,
     *     const: ?StringValue
     * }
     */
    private function stringOnly(Node $node): array
    {
        return [
            'format' => $this->readFormat(StringFormat::class, $node->get('format')),
            'minLength' => $this->nonNegative($node->get('minLength')) ?? 0,
            'maxLength' => $this->nonNegative($node->get('maxLength')),
            'pattern' => $node->get('pattern')->stringOrNull(),
            'contentEncoding' => $node->get('contentEncoding')->stringOrNull(),
            'contentMediaType' => $node->get('contentMediaType')->stringOrNull(),
            'contentSchema' => $node->has('contentSchema') ? $this->read($node->get('contentSchema')) : null,
            'default' => $this->stringValue($node->get('default')),
            'example' => $this->stringValue($node->get('example')),
            'const' => $this->stringValue($node->get('const')),
        ];
    }

    private function stringValue(Node $node): ?StringValue
    {
        return $node->isMissing() ? null : new StringValue($node->string());
    }

    /**
     * 3.0 ставит булев флаг рядом с minimum, 3.1 — само число вместо него.
     *
     * @return array{minimum: ?float, maximum: ?float, exclusiveMinimum: bool, exclusiveMaximum: bool}
     */
    private function range(Node $node): array
    {
        $minimum = $node->get('minimum')->floatOrNull();
        $maximum = $node->get('maximum')->floatOrNull();
        $exclusiveMinimum = $node->get('exclusiveMinimum');
        $exclusiveMaximum = $node->get('exclusiveMaximum');

        if (is_bool($exclusiveMinimum->value)) {
            $exclusiveMin = $exclusiveMinimum->value;
        } else {
            $exclusiveMin = !$exclusiveMinimum->isMissing();
            $minimum = $exclusiveMinimum->floatOrNull() ?? $minimum;
        }

        if (is_bool($exclusiveMaximum->value)) {
            $exclusiveMax = $exclusiveMaximum->value;
        } else {
            $exclusiveMax = !$exclusiveMaximum->isMissing();
            $maximum = $exclusiveMaximum->floatOrNull() ?? $maximum;
        }

        return [
            'minimum' => $minimum,
            'maximum' => $maximum,
            'exclusiveMinimum' => $exclusiveMin,
            'exclusiveMaximum' => $exclusiveMax,
        ];
    }

    /**
     * @return array{
     *     format: null|IntegerFormat|string,
     *     minimum: ?int,
     *     maximum: ?int,
     *     multipleOf: ?int,
     *     exclusiveMinimum: bool,
     *     exclusiveMaximum: bool,
     *     default: ?IntegerValue,
     *     example: ?IntegerValue,
     *     const: ?IntegerValue
     * }
     */
    private function integerOnly(Node $node): array
    {
        $range = $this->range($node);
        $multipleOf = $node->get('multipleOf')->floatOrNull();

        return [
            'format' => $this->readFormat(IntegerFormat::class, $node->get('format')),
            'minimum' => $range['minimum'] === null ? null : (int) $range['minimum'],
            'maximum' => $range['maximum'] === null ? null : (int) $range['maximum'],
            'multipleOf' => $multipleOf === null ? null : (int) $multipleOf,
            'exclusiveMinimum' => $range['exclusiveMinimum'],
            'exclusiveMaximum' => $range['exclusiveMaximum'],
            'default' => $this->integerValue($node->get('default')),
            'example' => $this->integerValue($node->get('example')),
            'const' => $this->integerValue($node->get('const')),
        ];
    }

    /**
     * @return array{
     *     format: null|NumberFormat|string,
     *     minimum: ?float,
     *     maximum: ?float,
     *     multipleOf: ?float,
     *     exclusiveMinimum: bool,
     *     exclusiveMaximum: bool,
     *     default: ?NumberValue,
     *     example: ?NumberValue,
     *     const: ?NumberValue
     * }
     */
    private function numberOnly(Node $node): array
    {
        $range = $this->range($node);

        return [
            'format' => $this->readFormat(NumberFormat::class, $node->get('format')),
            'minimum' => $range['minimum'],
            'maximum' => $range['maximum'],
            'multipleOf' => $node->get('multipleOf')->floatOrNull(),
            'exclusiveMinimum' => $range['exclusiveMinimum'],
            'exclusiveMaximum' => $range['exclusiveMaximum'],
            'default' => $this->numberValue($node->get('default')),
            'example' => $this->numberValue($node->get('example')),
            'const' => $this->numberValue($node->get('const')),
        ];
    }

    /**
     * @return array{
     *     items: ?AbstractSchema,
     *     minItems: int<0, max>,
     *     maxItems: null|int<0, max>,
     *     uniqueItems: bool,
     *     prefixItems: ?UntypedSchemas,
     *     contains: ?AbstractSchema,
     *     minContains: ?int,
     *     maxContains: ?int,
     *     unevaluatedItems: null|AbstractSchema|bool,
     *     default: ?ArrayValue,
     *     example: ?ArrayValue,
     *     const: ?ArrayValue
     * }
     */
    private function arrayOnly(Node $node): array
    {
        return [
            'items' => $node->has('items') ? $this->read($node->get('items')) : null,
            'minItems' => $this->nonNegative($node->get('minItems')) ?? 0,
            'maxItems' => $this->nonNegative($node->get('maxItems')),
            'uniqueItems' => $node->get('uniqueItems')->boolOr(false),
            'prefixItems' => $this->readSchemas($node->get('prefixItems')),
            'contains' => $node->has('contains') ? $this->read($node->get('contains')) : null,
            'minContains' => $node->get('minContains')->intOrNull(),
            'maxContains' => $node->get('maxContains')->intOrNull(),
            'unevaluatedItems' => $this->readSchemaOrBool($node->get('unevaluatedItems')),
            'default' => $this->arrayValue($node->get('default')),
            'example' => $this->arrayValue($node->get('example')),
            'const' => $this->arrayValue($node->get('const')),
        ];
    }

    /**
     * @return array{
     *     properties: ?Properties,
     *     minProperties: int<0, max>,
     *     maxProperties: null|int<0, max>,
     *     additionalProperties: AbstractSchema|bool,
     *     patternProperties: ?PatternProperties,
     *     propertyNames: ?AbstractSchema,
     *     dependentRequired: ?DependentRequired,
     *     dependentSchemas: ?UntypedSchemas,
     *     unevaluatedProperties: null|AbstractSchema|bool,
     *     default: ?ObjectValue,
     *     example: ?ObjectValue,
     *     const: ?ObjectValue
     * }
     */
    private function objectOnly(Node $node): array
    {
        $required = $node->get('required')->strings();
        $properties = [];

        foreach ($node->get('properties')->map() as $name => $item) {
            $properties[$name] = new Property(
                schema: $this->read($item),
                required: in_array($name, $required, true),
            );
        }

        $patternProperties = [];

        foreach ($node->get('patternProperties')->map() as $pattern => $item) {
            $patternProperties[$pattern] = $this->read($item);
        }

        $dependentRequired = [];

        foreach ($node->get('dependentRequired')->map() as $name => $item) {
            $dependentRequired[$name] = new Strings(...$item->strings());
        }

        return [
            'properties' => $properties === [] ? null : new Properties(...$properties),
            'minProperties' => $this->nonNegative($node->get('minProperties')) ?? 0,
            'maxProperties' => $this->nonNegative($node->get('maxProperties')),
            // по умолчанию дополнительные свойства разрешены, и параметр не nullable
            'additionalProperties' => $this->readSchemaOrBool($node->get('additionalProperties')) ?? true,
            'patternProperties' => $patternProperties === [] ? null : new PatternProperties(...$patternProperties),
            'propertyNames' => $node->has('propertyNames') ? $this->read($node->get('propertyNames')) : null,
            'dependentRequired' => $dependentRequired === [] ? null : new DependentRequired(...$dependentRequired),
            'dependentSchemas' => $this->readSchemas($node->get('dependentSchemas')),
            'unevaluatedProperties' => $this->readSchemaOrBool($node->get('unevaluatedProperties')),
            'default' => $this->objectValue($node->get('default')),
            'example' => $this->objectValue($node->get('example')),
            'const' => $this->objectValue($node->get('const')),
        ];
    }

    private function readSchemaOrBool(Node $node): AbstractSchema|bool|null
    {
        if ($node->isMissing()) {
            return null;
        }

        return is_bool($node->value) ? $node->value : $this->read($node);
    }

    private function readSchemas(Node $node): ?UntypedSchemas
    {
        if ($node->isMissing()) {
            return null;
        }

        if (is_array($node->value)) {
            $items = array_map($this->read(...), $node->list());

            return $items === [] ? null : new UntypedSchemas(...$items);
        }

        $items = [];

        foreach ($node->map() as $name => $item) {
            $items[$name] = $this->read($item);
        }

        return $items === [] ? null : new UntypedSchemas(...$items);
    }

    private function readDynamicRef(Node $node): ?AbstractSchema
    {
        $ref = $node->get('$dynamicRef')->stringOrNull();

        if ($ref === null) {
            return null;
        }

        $anchor = ltrim($ref, '#');

        foreach ($this->registry->pointers($this->registry->currentFile() . '#') as $pointer) {
            $candidate = $this->registry->node($pointer);

            if ($candidate?->get('$dynamicAnchor')->stringOrNull() === $anchor) {
                return $this->registry->resolveSchema($pointer);
            }
        }

        throw $node->get('$dynamicRef')->unexpected(sprintf('a schema declaring $dynamicAnchor "%s"', $anchor));
    }

    private function readVocabulary(Node $node): ?Vocabularies
    {
        $items = [];

        foreach ($node->map() as $uri => $item) {
            $items[$uri] = $item->bool();
        }

        return $items === [] ? null : new Vocabularies(...$items);
    }

    private function readDiscriminator(Node $node): ?Discriminator
    {
        if ($node->isMissing()) {
            return null;
        }

        $mapping = [];

        foreach ($node->get('mapping')->map() as $key => $item) {
            $mapping[$key] = $this->registry->resolveSchema(
                $this->registry->pointerOf($item->string(), $item),
            );
        }

        return new Discriminator(
            propertyName: $node->get('propertyName')->string(),
            mapping: $mapping === [] ? null : new UntypedSchemas(...$mapping),
        );
    }

    private function readAccess(Node $node): ?Access
    {
        if ($node->get('readOnly')->boolOr(false)) {
            return Access::ReadOnly;
        }

        return $node->get('writeOnly')->boolOr(false) ? Access::WriteOnly : null;
    }

    private function readExternalDocs(Node $node): ?ExternalDocs
    {
        if ($node->isMissing()) {
            return null;
        }

        return new ExternalDocs(
            url: $node->get('url')->string(),
            description: $node->get('description')->stringOrNull(),
        );
    }

    private function readXml(Node $node): ?Xml
    {
        if ($node->isMissing()) {
            return null;
        }

        return new Xml(
            name: $node->get('name')->stringOrNull(),
            namespace: $node->get('namespace')->stringOrNull(),
            prefix: $node->get('prefix')->stringOrNull(),
            attribute: $node->get('attribute')->boolOr(false),
            wrapped: $node->get('wrapped')->boolOr(false),
        );
    }

    private function nativeOf(Node $node): AbstractValues|bool|float|int|string|null
    {
        $value = $node->value;

        if (is_array($value) || $value instanceof stdClass) {
            return $this->readValues($node);
        }

        return is_scalar($value) || $value === null
            ? $value
            : throw $node->unexpected('a scalar or a structure');
    }

    /**
     * format — открытое значение: незнакомая строка остаётся строкой,
     * а не считается ошибкой документа.
     *
     * @template T of BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return null|string|T
     */
    private function readFormat(string $enum, Node $node): object|string|null
    {
        $value = $node->stringOrNull();

        return $value === null ? null : $enum::tryFrom($value) ?? $value;
    }
}
