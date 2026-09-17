<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Reader;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractEnumSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValue;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValues;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Access;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Discriminator;
use EugeneErg\OpenApi\Components\Schemas\Abstract\JsonValue;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Vocabularies;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Xml;
use EugeneErg\OpenApi\Components\Schemas\Array\Arrays;
use EugeneErg\OpenApi\Components\Schemas\Array\EnumSchema as ArrayEnumSchema;
use EugeneErg\OpenApi\Components\Schemas\Array\OpenapiArray;
use EugeneErg\OpenApi\Components\Schemas\Array\Schema as ArraySchema;
use EugeneErg\OpenApi\Components\Schemas\Array\Value as ArrayValue;
use EugeneErg\OpenApi\Components\Schemas\Boolean\EnumSchema as BooleanEnumSchema;
use EugeneErg\OpenApi\Components\Schemas\Boolean\Schema as BooleanSchema;
use EugeneErg\OpenApi\Components\Schemas\Boolean\Value as BooleanValue;
use EugeneErg\OpenApi\Components\Schemas\Integer\EnumSchema as IntegerEnumSchema;
use EugeneErg\OpenApi\Components\Schemas\Integer\Integers;
use EugeneErg\OpenApi\Components\Schemas\Integer\Schema as IntegerSchema;
use EugeneErg\OpenApi\Components\Schemas\Integer\Value as IntegerValue;
use EugeneErg\OpenApi\Components\Schemas\Null\Schema as NullSchema;
use EugeneErg\OpenApi\Components\Schemas\Number\EnumSchema as NumberEnumSchema;
use EugeneErg\OpenApi\Components\Schemas\Number\Numbers;
use EugeneErg\OpenApi\Components\Schemas\Number\Schema as NumberSchema;
use EugeneErg\OpenApi\Components\Schemas\Number\Value as NumberValue;
use EugeneErg\OpenApi\Components\Schemas\Object\DependentRequired;
use EugeneErg\OpenApi\Components\Schemas\Object\EnumSchema as ObjectEnumSchema;
use EugeneErg\OpenApi\Components\Schemas\Object\Objects;
use EugeneErg\OpenApi\Components\Schemas\Object\OpenapiObject;
use EugeneErg\OpenApi\Components\Schemas\Object\PatternProperties;
use EugeneErg\OpenApi\Components\Schemas\Object\Properties;
use EugeneErg\OpenApi\Components\Schemas\Object\Property;
use EugeneErg\OpenApi\Components\Schemas\Object\Schema as ObjectSchema;
use EugeneErg\OpenApi\Components\Schemas\Object\Value as ObjectValue;
use EugeneErg\OpenApi\Components\Schemas\String\EnumSchema as StringEnumSchema;
use EugeneErg\OpenApi\Components\Schemas\String\Schema as StringSchema;
use EugeneErg\OpenApi\Components\Schemas\String\Strings;
use EugeneErg\OpenApi\Components\Schemas\String\Value as StringValue;
use EugeneErg\OpenApi\Components\Schemas\Untyped\EnumSchema as UntypedEnumSchema;
use EugeneErg\OpenApi\Components\Schemas\Untyped\Schema as UntypedSchema;
use EugeneErg\OpenApi\Components\Schemas\Untyped\Schemas as UntypedSchemas;
use EugeneErg\OpenApi\Components\Schemas\Untyped\Value as UntypedValue;
use EugeneErg\OpenApi\Components\Schemas\Untyped\Values;
use EugeneErg\OpenApi\Exceptions\InvalidDocumentOpenapiException;
use EugeneErg\OpenApi\Exceptions\InvalidSchemaOpenapiException;
use EugeneErg\OpenApi\Extensions;
use EugeneErg\OpenApi\ExternalDocs;
use EugeneErg\OpenApi\Serialization\Structure;
use stdClass;

use function array_key_exists;
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
    private const array ENUM_KEPT = [
        'enum', 'const', 'type', 'nullable', 'example', 'examples', 'default',
        'title', 'description', 'readOnly', 'writeOnly', 'deprecated', 'externalDocs', 'xml',
        '$comment', '$defs', '$id', '$anchor', '$dynamicAnchor', '$vocabulary', '$schema',
    ];

    /**
     * Keyword → вид значений, к которому он применим (только проверяемые простые слова).
     */
    private const array KEYWORD_KINDS = [
        'minLength' => 'string', 'maxLength' => 'string', 'pattern' => 'string',
        'minimum' => 'number', 'maximum' => 'number', 'multipleOf' => 'number',
        'exclusiveMinimum' => 'number', 'exclusiveMaximum' => 'number',
        'minItems' => 'array', 'maxItems' => 'array', 'uniqueItems' => 'array',
        'minProperties' => 'object', 'maxProperties' => 'object', 'required' => 'object',
    ];

    private const array APPLICATORS = [
        'allOf', 'anyOf', 'oneOf', 'not', 'if', 'then', 'else', 'discriminator', '$ref', '$dynamicRef',
        'properties', 'additionalProperties', 'patternProperties', 'propertyNames',
        'dependentRequired', 'dependentSchemas', 'unevaluatedProperties',
        'items', 'prefixItems', 'contains', 'minContains', 'maxContains', 'unevaluatedItems',
        'contentMediaType', 'contentEncoding', 'contentSchema',
    ];

    /**
     * Слова, привязанные к типу, но не проверяющие значение поштучно.
     */
    private const array TYPE_KEYWORDS = [
        'format' => null, 'items' => 'array', 'prefixItems' => 'array', 'contains' => 'array',
        'minContains' => 'array', 'maxContains' => 'array', 'unevaluatedItems' => 'array',
        'properties' => 'object', 'patternProperties' => 'object', 'propertyNames' => 'object',
        'additionalProperties' => 'object', 'dependentRequired' => 'object', 'dependentSchemas' => 'object',
        'unevaluatedProperties' => 'object',
        'contentEncoding' => 'string', 'contentMediaType' => 'string', 'contentSchema' => 'string',
    ];

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

    /**
     * Компонент-схема. Если это лишь ссылка на другую схему (псевдоним), он всё равно
     * должен остаться отдельным объектом, иначе два имени сольются в одно.
     */
    public function readComponentSchema(Node $node): AbstractSchema
    {
        $schema = $this->readSchema($node);

        if ($node->has('$ref') && array_keys(self::members($node->value)) === ['$ref']) {
            return new UntypedSchema(allOf: new UntypedSchemas($schema));
        }

        return $schema;
    }

    public function readSchema(Node $node): AbstractSchema
    {
        if ($node->has('$ref')) {
            $target = $this->registry->resolveSchema(
                $this->registry->pointerOf($node->get('$ref')->string(), $node),
            );
            $siblings = array_filter(
                array_keys(self::members($node->value)),
                static fn (int|string $keyword): bool => $keyword !== '$ref' && !str_starts_with((string) $keyword, 'x-'),
            );

            // в 3.0 соседи $ref ничего не значат
            if ($siblings === [] || !$this->registry->isV31()) {
                return $target;
            }

            // в 3.1 $ref — одно из ключевых слов; та же схема без него — это allOf со ссылкой
            $rest = new stdClass();
            $rest->allOf = [(object) ['$ref' => $node->get('$ref')->string()]];

            foreach (self::members($node->value) as $keyword => $argument) {
                if ($keyword === 'allOf') {
                    $rest->allOf = [...$rest->allOf, ...(array) $argument];
                } elseif ($keyword !== '$ref') {
                    $rest->{$keyword} = $argument;
                }
            }

            return $this->readSchema(new Node($rest, $node->path));
        }

        [$declared, $nullableFlag] = $this->readType($node);
        $types = array_values(array_filter($declared, static fn (string $item): bool => $item !== 'null'));
        $nullable = $nullableFlag || $types !== $declared;

        // Пакет описывает схемы по типу, поэтому объединение нескольких типов
        // и схема одного лишь null разбираются отдельно.
        if ($declared !== [] && $types === []) {
            return new NullSchema(...$this->common($node, false));
        }

        if (count($types) > 1) {
            return $this->readUnion($node, $types, $nullable);
        }

        $type = $types[0] ?? null;
        $common = $this->common($node, $nullable);

        if ($node->has('enum') || $node->has('const')) {
            // JSON Schema разрешает пустой перечень: ему не подходит ничего,
            // и равнозначная запись этого — `not: {}`
            return $node->get('enum')->isPresent() && $node->get('enum')->list() === []
                ? $this->readNothing($node)
                : $this->readEnum($node, $type, $nullable, $common);
        }

        // type может отсутствовать, а проверки — быть: тогда выбираем ветку
        // по ключевым словам и не объявляем type в выводе
        $declareType = $type !== null;

        if ($type === null) {
            $shapes = self::inferShapes($node);

            if (count($shapes) > 1) {
                return $this->readShapes($node, $shapes);
            }

            $type = $shapes[0] ?? null;
        }

        return match ($type) {
            'string' => new StringSchema(...[...$common, ...$this->stringOnly($node), 'declareType' => $declareType]),
            'integer' => new IntegerSchema(...[...$common, ...$this->integerOnly($node), 'declareType' => $declareType]),
            'number' => new NumberSchema(...[...$common, ...$this->numberOnly($node), 'declareType' => $declareType]),
            'boolean' => new BooleanSchema(...[...$common, ...$this->booleanOnly($node)]),
            'array' => new ArraySchema(...[...$common, ...$this->arrayOnly($node), 'declareType' => $declareType]),
            'object' => new ObjectSchema(...[...$common, ...$this->objectOnly($node), 'declareType' => $declareType]),
            default => new UntypedSchema(...[
                ...$common,
                'default' => $this->untypedValue($node->get('default')),
                'example' => $this->untypedValue($node->get('example')),
            ]),
        };
    }

    /**
     * Значение в типизированной схеме должно быть того же типа, поэтому класс
     * обёртки выбирается по type схемы.
     */
    public function readValue(Node $node, ?string $type = null): ?AbstractValue
    {
        if (!$node->isPresent()) {
            return null;
        }

        // null — тоже значение: пример пустого ответа или default nullable-поля
        return match ($type) {
            'string' => $this->stringValue($node),
            'integer' => $this->integerValue($node),
            'number' => $this->numberValue($node),
            'boolean' => $this->booleanValue($node),
            'array' => $this->arrayValue($node),
            'object' => $this->objectValue($node),
            default => $this->untypedValue($node),
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

        return OpenapiObject::fromArray($items);
    }

    /**
     * Пример, который не подходит под тип схемы, ни на что не влияет: он лишь
     * иллюстрирует, и такой иллюстрации нет места в документе. Узел без значения
     * означает «примера нет».
     */
    private function example(Node $schema, string $type): Node
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

    private function untypedValue(Node $node): ?UntypedValue
    {
        return $node->isPresent() ? new UntypedValue($this->nativeOf($node)) : null;
    }

    /**
     * Ветки для схемы без type: их задают слова, применимые к значениям одного типа.
     *
     * `{"minLength": 3}` — это «строка длиннее двух», а значение любого другого типа
     * такой схеме подходит: проверка строки к нему не применяется. Поэтому тип здесь
     * не объявляется, а слово всё равно должно попасть в документ.
     *
     * @return list<string> в постоянном порядке, чтобы вывод не зависел от порядка слов
     */
    private static function inferShapes(Node $node): array
    {
        $shapes = [];

        foreach (array_keys(self::members($node->value)) as $keyword) {
            $shape = self::KEYWORD_KINDS[$keyword] ?? self::TYPE_KEYWORDS[$keyword] ?? null;

            if ($shape !== null && !in_array($shape, $shapes, true)) {
                $shapes[] = $shape;
            }
        }

        return array_values(array_filter(
            ['string', 'number', 'array', 'object'],
            static fn (string $shape): bool => in_array($shape, $shapes, true),
        ));
    }

    /**
     * Схема без type, в которой есть слова про значения разных типов:
     * `{"minLength": 3, "minItems": 1}` — это `allOf` из проверки строк и проверки
     * массивов. Одним классом это не записать: каждый описывает значения своего типа.
     *
     * @param list<string> $shapes
     */
    private function readShapes(Node $node, array $shapes): AbstractSchema
    {
        $branches = [];
        $shared = new stdClass();

        foreach (self::members($node->value) as $keyword => $argument) {
            if ((self::KEYWORD_KINDS[$keyword] ?? self::TYPE_KEYWORDS[$keyword] ?? null) === null) {
                $shared->{$keyword} = $argument;
            }
        }

        foreach ($shapes as $shape) {
            $branch = new stdClass();

            foreach (self::members($node->value) as $keyword => $argument) {
                if ((self::KEYWORD_KINDS[$keyword] ?? self::TYPE_KEYWORDS[$keyword] ?? null) === $shape) {
                    $branch->{$keyword} = $argument;
                }
            }

            $branches[] = $this->readSchema(new Node($branch, $node->path));
        }

        if (self::members($shared) !== []) {
            array_unshift($branches, $this->readSchema(new Node($shared, $node->path)));
        }

        return new UntypedSchema(allOf: new UntypedSchemas(...$branches));
    }

    /**
     * Схема, которой не подходит ни одно значение: остальные слова плюс `not: {}`.
     */
    private function readNothing(Node $node): AbstractSchema
    {
        $rest = new stdClass();

        foreach (self::members($node->value) as $keyword => $argument) {
            if ($keyword !== 'enum') {
                $rest->{$keyword} = $argument;
            }
        }

        $rest->not = new stdClass();

        return $this->readSchema(new Node($rest, $node->path));
    }

    /**
     * Объединение типов: `{"type": ["string", "integer"], "maxLength": 5}` — это
     * `anyOf` из схемы строки с maxLength и схемы целого. Слова, применимые к одному
     * из перечисленных типов, уходят в его ветку, остальное остаётся общим.
     *
     * @param list<string> $types
     */
    private function readUnion(Node $node, array $types, bool $nullable): AbstractSchema
    {
        if ($nullable) {
            $types[] = 'null';
        }

        $branches = [];
        $shared = new stdClass();

        foreach (self::members($node->value) as $keyword => $argument) {
            $keyword = (string) $keyword;

            if ($keyword !== 'type' && $keyword !== 'nullable' && self::branchOf($keyword, $types) === null) {
                $shared->{$keyword} = $argument;
            }
        }

        foreach ($types as $type) {
            $branch = new stdClass();
            $branch->type = $type;

            foreach (self::members($node->value) as $keyword => $argument) {
                if (self::branchOf((string) $keyword, $types) === $type) {
                    $branch->{$keyword} = $argument;
                }
            }

            $branches[] = $type === 'null'
                ? new NullSchema()
                : $this->readSchema(new Node($branch, $node->path . '/type/' . $type));
        }

        $union = count($branches) === 1
            ? $branches[0]
            : new UntypedSchema(anyOf: new UntypedSchemas(...$branches));

        return self::members($shared) === []
            ? $union
            : new UntypedSchema(allOf: new UntypedSchemas($this->readSchema(new Node($shared, $node->path)), $union));
    }

    /**
     * Тип, к значениям которого применимо ключевое слово, если он один из перечисленных.
     *
     * @param list<string> $types
     */
    private static function branchOf(string $keyword, array $types): ?string
    {
        $kind = self::KEYWORD_KINDS[$keyword] ?? self::TYPE_KEYWORDS[$keyword] ?? null;

        if ($kind === null) {
            return null;
        }

        // integer — частный случай number, поэтому число ищется среди обоих
        foreach ($kind === 'number' ? ['number', 'integer'] : [$kind] as $candidate) {
            if (in_array($candidate, $types, true)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Объявленные типы и флаг `nullable`.
     *
     * В 3.1 `type` может быть массивом, и `null` в нём — способ выразить nullable;
     * `type: "null"` без других типов означает, что допустим только null.
     *
     * @return array{list<string>, bool} объявленные типы как есть; флаг nullable
     */
    private function readType(Node $node): array
    {
        $type = $node->get('type');
        $nullable = $node->get('nullable')->boolOr(false);

        if ($type->isMissing()) {
            return [[], $nullable];
        }

        $types = is_string($type->value) ? [$type->value] : $type->strings();

        return $types === [] ? throw $type->unexpected('at least one type') : [$types, $nullable];
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
     *     else: ?AbstractSchema,
     *     extensions: ?Extensions,
     *     format: ?string
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
            'extensions' => $node->extensions(),
            // JSON Schema разрешает format у значения любого типа
            'format' => $node->get('format')->stringOrNull(),
        ];
    }

    /**
     * @return array{default: ?BooleanValue, example: ?BooleanValue}
     */
    private function booleanOnly(Node $node): array
    {
        return [
            'default' => $this->booleanValue($node->get('default')),
            'example' => $this->booleanValue($this->example($node, 'boolean')),
        ];
    }

    private function booleanValue(Node $node): ?BooleanValue
    {
        return $node->isPresent() ? new BooleanValue($node->value === null ? null : $node->bool()) : null;
    }

    private function integerValue(Node $node): ?IntegerValue
    {
        return $node->isPresent() ? new IntegerValue($node->value === null ? null : $node->int()) : null;
    }

    private function numberValue(Node $node): ?NumberValue
    {
        return $node->isPresent() ? new NumberValue($node->value === null ? null : $node->floatOrNull()) : null;
    }

    private function arrayValue(Node $node): ?ArrayValue
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

    private function objectValue(Node $node): ?ObjectValue
    {
        if (!$node->isPresent()) {
            return null;
        }

        if ($node->value === null) {
            return new ObjectValue(null);
        }

        $values = $this->readValues($node);

        return new ObjectValue($values instanceof OpenapiObject ? $values : throw $node->unexpected('a mapping'));
    }

    /**
     * Перечисление (`enum` и/или `const`).
     *
     * У схемы-перечисления остаются только аннотации (см. AbstractEnumSchema), поэтому
     * остальные ключевые слова разбираются так:
     * - `example`/`examples` и слова, неприменимые к типу значений, ничего не меняют — отбрасываются;
     * - простые проверки (длина, pattern, границы, required…) проверяются на каждом значении:
     *   если все значения проходят, слово ничего не меняет и отбрасывается, иначе документ
     *   противоречив — часть значений недостижима;
     * - всё, что так не проверить (композиция, properties, items…), сохраняется без потерь
     *   эквивалентной записью `allOf: [остальная схема, перечисление]`.
     *
     * `nullable: true` без null в перечне по букве 3.0.3 ничего не разрешает, но так пишут
     * постоянно и имеют в виду именно nullable. Читаем по намерению: при записи null
     * окажется и во флаге, и в перечне, и документ станет однозначным для любого инструмента.
     *
     * @param array{
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
     *     else: ?AbstractSchema,
     *     extensions: ?Extensions,
     *     format: ?string
     * } $common
     */
    private function readEnum(Node $node, ?string $type, bool $nullable, array $common): AbstractSchema
    {
        $values = $this->enumValues($node);
        $withNull = array_filter($values, static fn (Node $value): bool => $value->value === null) !== [];
        $values = array_values(array_filter($values, static fn (Node $value): bool => $value->value !== null));

        if ($withNull && $type !== null && !$nullable) {
            throw self::contradiction($node, sprintf('null is listed in enum, but type "%s" does not allow it', $type));
        }

        $nullable = $nullable || $withNull;
        $type = $this->enumType($node, $type, $values);
        $split = [];

        foreach (self::members($node->value) as $keyword => $unused) {
            $keyword = (string) $keyword;

            if (!in_array($keyword, self::ENUM_KEPT, true) && !$this->assertionHolds($node, $keyword, $type, $values)) {
                $split[] = $keyword;
            }
        }

        if ($split !== []) {
            return $this->splitEnum($node, $type, $nullable, $values);
        }

        $annotations = [
            'title' => $common['title'],
            'description' => $common['description'],
            'nullable' => $nullable,
            'access' => $common['access'],
            'deprecated' => $common['deprecated'],
            'externalDocs' => $common['externalDocs'],
            'xml' => $common['xml'],
            'comment' => $common['comment'],
            'defs' => $common['defs'],
            'id' => $common['id'],
            'anchor' => $common['anchor'],
            'dynamicAnchor' => $common['dynamicAnchor'],
            'vocabulary' => $common['vocabulary'],
            'extensions' => $common['extensions'],
            'format' => $common['format'],
        ];

        return $this->buildEnum($node, $type, $values, $annotations);
    }

    /**
     * @return list<Node> значения перечня без повторов; `const` вместе с `enum` — их пересечение
     */
    private function enumValues(Node $node): array
    {
        $result = [];

        foreach ($node->has('enum') ? $node->get('enum')->list() : [$node->get('const')] as $value) {
            $result[JsonValue::key($value->value)] ??= $value;
        }

        if ($node->has('const') && $node->has('enum')) {
            $const = $node->get('const');

            if (!isset($result[JsonValue::key($const->value)])) {
                throw self::contradiction($node, 'const is not one of the enum values');
            }

            return [$const];
        }

        if ($result === []) {
            throw $node->get('enum')->unexpected('a non-empty enum');
        }

        return array_values($result);
    }

    /**
     * @param list<Node> $values
     */
    private function enumType(Node $node, ?string $type, array $values): ?string
    {
        if ($type !== null) {
            foreach ($values as $value) {
                $fits = $type === 'integer'
                    ? JsonValue::isInteger($value->value)
                    : JsonValue::kind($value->value) === $type;

                if (!$fits) {
                    throw self::contradiction($value, sprintf('the value is not of type "%s"', $type));
                }
            }

            return $type;
        }

        $kinds = array_unique(array_map(static fn (Node $value): string => JsonValue::kind($value->value), $values));

        if (count($kinds) !== 1) {
            return null;
        }

        $kind = $kinds[array_key_first($kinds)];
        $integers = array_filter($values, static fn (Node $value): bool => JsonValue::isInteger($value->value));

        return $kind === 'number' && count($integers) === count($values) ? 'integer' : $kind;
    }

    /**
     * true — слово не меняет множество значений и может быть отброшено.
     *
     * @param list<Node> $values
     */
    private function assertionHolds(Node $node, string $keyword, ?string $type, array $values): bool
    {
        if (str_starts_with($keyword, 'x-')) {
            return true;
        }

        $applies = self::KEYWORD_KINDS[$keyword] ?? null;

        // неизвестное слово: пакет его не моделирует и в обычных схемах
        if ($applies === null) {
            return !in_array($keyword, self::APPLICATORS, true);
        }

        $argument = $node->get($keyword);

        foreach ($values as $value) {
            $kind = JsonValue::kind($value->value);

            // слово действует только на значения своего вида
            if ($kind !== $applies) {
                continue;
            }

            $holds = $this->holds($keyword, $argument, $value->value, $node);

            if ($holds === null) {
                return false;
            }

            if (!$holds) {
                throw self::contradiction(
                    $value,
                    sprintf('the value is excluded by "%s", so it can never be valid', $keyword),
                );
            }
        }

        return true;
    }

    /**
     * @return null|bool null — проверить нельзя
     */
    private function holds(string $keyword, Node $argument, mixed $value, Node $schema): ?bool
    {
        $string = is_string($value) ? $value : '';
        $number = is_int($value) || is_float($value) ? (float) $value : 0.0;

        switch ($keyword) {
            case 'minLength':
                return mb_strlen($string) >= $argument->int();

            case 'maxLength':
                return mb_strlen($string) <= $argument->int();

            case 'pattern':
                set_error_handler(static fn (): bool => true);

                try {
                    $matched = preg_match("\x01" . $argument->string() . "\x01u", $string);
                } finally {
                    restore_error_handler();
                }

                return $matched === false ? null : $matched === 1;

            case 'minimum':
            case 'maximum':
                $bound = $argument->floatOrNull() ?? throw $argument->unexpected('a number');
                $exclusive = $schema->get('exclusive' . ucfirst($keyword))->value === true;

                return $keyword === 'minimum'
                    ? ($exclusive ? $number > $bound : $number >= $bound)
                    : ($exclusive ? $number < $bound : $number <= $bound);

            case 'exclusiveMinimum':
            case 'exclusiveMaximum':
                if (is_bool($argument->value)) {
                    return true;
                }

                $bound = $argument->floatOrNull() ?? throw $argument->unexpected('a number or a boolean');

                return $keyword === 'exclusiveMinimum' ? $number > $bound : $number < $bound;

            case 'multipleOf':
                $step = $argument->floatOrNull() ?? throw $argument->unexpected('a number');
                $ratio = $number / $step;

                return abs($ratio - round($ratio)) < 1e-9;

            case 'minItems':
                return count(self::members($value)) >= $argument->int();

            case 'maxItems':
                return count(self::members($value)) <= $argument->int();

            case 'uniqueItems':
                if ($argument->value !== true) {
                    return true;
                }

                $keys = array_map(JsonValue::key(...), self::members($value));

                return count($keys) === count(array_unique($keys));

            case 'minProperties':
                return count(self::members($value)) >= $argument->int();

            case 'maxProperties':
                return count(self::members($value)) <= $argument->int();

            case 'required':
                return array_diff($argument->strings(), array_map('strval', array_keys(self::members($value)))) === [];
        }

        return null;
    }

    /**
     * Эквивалентная запись без потерь: остальная схема и перечисление через allOf.
     *
     * @param list<Node> $values
     */
    private function splitEnum(Node $node, ?string $type, bool $nullable, array $values): AbstractSchema
    {
        $rest = new stdClass();

        foreach (self::members($node->value) as $keyword => $argument) {
            if ($keyword !== 'enum' && $keyword !== 'const') {
                $rest->{$keyword} = $argument;
            }
        }

        return new UntypedSchema(allOf: new UntypedSchemas(
            $this->readSchema(new Node($rest, $node->path)),
            $this->buildEnum($node, $type, $values, [
                'title' => null,
                'description' => null,
                'nullable' => $nullable,
                'access' => null,
                'deprecated' => false,
                'externalDocs' => null,
                'xml' => null,
                'comment' => null,
                'defs' => null,
                'id' => null,
                'anchor' => null,
                'dynamicAnchor' => null,
                'vocabulary' => null,
                'extensions' => null,
                'format' => null,
            ], withDefault: false),
        ));
    }

    /**
     * @param list<Node> $values
     * @param array{
     *     title: ?string,
     *     description: ?string,
     *     nullable: bool,
     *     access: ?Access,
     *     deprecated: bool,
     *     externalDocs: ?ExternalDocs,
     *     xml: ?Xml,
     *     comment: ?string,
     *     defs: ?UntypedSchemas,
     *     id: ?string,
     *     anchor: ?string,
     *     dynamicAnchor: ?string,
     *     vocabulary: ?Vocabularies,
     *     extensions: ?Extensions,
     *     format: ?string
     * } $annotations
     */
    private function buildEnum(Node $node, ?string $type, array $values, array $annotations, bool $withDefault = true): AbstractSchema
    {
        $default = $withDefault ? $node->get('default') : new Node(null, $node->path, present: false);

        try {
            switch ($type) {
                case 'string':
                    $strings = new Strings(...array_map(static fn (Node $value): string => $value->string(), $values));

                    return new StringEnumSchema(
                        ...$annotations,
                        enums: $strings,
                        default: $this->stringValue($default),
                        contentEncoding: $node->get('contentEncoding')->stringOrNull(),
                        contentMediaType: $node->get('contentMediaType')->stringOrNull(),
                        contentSchema: $node->has('contentSchema') ? $this->read($node->get('contentSchema')) : null,
                    );

                case 'integer':
                    $integers = new Integers(...array_map(self::integer(...), $values));

                    return new IntegerEnumSchema(
                        ...$annotations,
                        enums: $integers,
                        default: $this->integerValue($default),
                    );

                case 'number':
                    $numbers = new Numbers(...array_map(self::number(...), $values));

                    return new NumberEnumSchema(
                        ...$annotations,
                        enums: $numbers,
                        default: $this->numberValue($default),
                    );

                case 'boolean':
                    $booleanDefault = $this->booleanValue($default);

                    // перечень из обоих значений ничего не ограничивает
                    return count($values) === 2
                        ? new BooleanSchema(...$annotations, default: $booleanDefault)
                        : new BooleanEnumSchema(...$annotations, value: $values[0]->bool(), default: $booleanDefault);

                case 'array':
                    $arrays = new Arrays(...array_map($this->listOf(...), $values));

                    return new ArrayEnumSchema(...$annotations, enums: $arrays, default: $this->arrayValue($default));

                case 'object':
                    $objects = new Objects(...array_map($this->mapOf(...), $values));

                    return new ObjectEnumSchema(...$annotations, enums: $objects, default: $this->objectValue($default));

                default:
                    $mixed = new Values(...array_map($this->nativeOf(...), $values));
                    $mixedDefault = $this->untypedValue($default);

                    return new UntypedEnumSchema(...$annotations, enums: $mixed, default: $mixedDefault);
            }
        } catch (InvalidSchemaOpenapiException $exception) {
            throw self::contradiction($node, lcfirst(rtrim($exception->getMessage(), '.')));
        }
    }

    private function listOf(Node $node): OpenapiArray
    {
        $values = $this->readValues($node);

        return $values instanceof OpenapiArray ? $values : throw $node->unexpected('a list');
    }

    private function mapOf(Node $node): OpenapiObject
    {
        $values = $this->readValues($node);

        return $values instanceof OpenapiObject ? $values : throw $node->unexpected('a mapping');
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function members(mixed $value): array
    {
        return $value instanceof stdClass ? Structure::vars($value) : (array) $value;
    }

    private static function integer(Node $node): int
    {
        return is_int($node->value) ? $node->value : (int) ($node->floatOrNull() ?? throw $node->unexpected('an integer'));
    }

    private static function number(Node $node): float|int
    {
        return $node->numberOrNull() ?? throw $node->unexpected('a number');
    }

    private static function contradiction(Node $node, string $reason): InvalidDocumentOpenapiException
    {
        return new InvalidDocumentOpenapiException(sprintf('%s: %s.', $node->path, $reason));
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
     *     minLength: int<0, max>,
     *     maxLength: null|int<0, max>,
     *     pattern: ?string,
     *     contentEncoding: ?string,
     *     contentMediaType: ?string,
     *     contentSchema: ?AbstractSchema,
     *     default: ?StringValue,
     *     example: ?StringValue,
     * }
     */
    private function stringOnly(Node $node): array
    {
        return [
            'minLength' => $this->nonNegative($node->get('minLength')) ?? 0,
            'maxLength' => $this->nonNegative($node->get('maxLength')),
            'pattern' => $node->get('pattern')->stringOrNull(),
            'contentEncoding' => $node->get('contentEncoding')->stringOrNull(),
            'contentMediaType' => $node->get('contentMediaType')->stringOrNull(),
            'contentSchema' => $node->has('contentSchema') ? $this->read($node->get('contentSchema')) : null,
            'default' => $this->stringValue($node->get('default')),
            'example' => $this->stringValue($this->example($node, 'string')),
        ];
    }

    private function stringValue(Node $node): ?StringValue
    {
        return $node->isPresent() ? new StringValue($node->value === null ? null : $node->string()) : null;
    }

    /**
     * 3.0 ставит булев флаг рядом с minimum, 3.1 — само число вместо него.
     *
     * @return array{
     *     minimum: null|float|int,
     *     maximum: null|float|int,
     *     exclusiveMinimum: bool,
     *     exclusiveMaximum: bool
     * }
     */
    private function range(Node $node): array
    {
        $minimum = $node->get('minimum')->numberOrNull();
        $maximum = $node->get('maximum')->numberOrNull();
        $exclusiveMinimum = $node->get('exclusiveMinimum');
        $exclusiveMaximum = $node->get('exclusiveMaximum');

        if (is_bool($exclusiveMinimum->value)) {
            $exclusiveMin = $exclusiveMinimum->value;
        } else {
            $exclusiveMin = !$exclusiveMinimum->isMissing();
            $minimum = $exclusiveMinimum->numberOrNull() ?? $minimum;
        }

        if (is_bool($exclusiveMaximum->value)) {
            $exclusiveMax = $exclusiveMaximum->value;
        } else {
            $exclusiveMax = !$exclusiveMaximum->isMissing();
            $maximum = $exclusiveMaximum->numberOrNull() ?? $maximum;
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
     *     minimum: null|float|int,
     *     maximum: null|float|int,
     *     multipleOf: null|float|int,
     *     exclusiveMinimum: bool,
     *     exclusiveMaximum: bool,
     *     default: ?IntegerValue,
     *     example: ?IntegerValue,
     * }
     */
    private function integerOnly(Node $node): array
    {
        $range = $this->range($node);

        return [
            'minimum' => $range['minimum'],
            'maximum' => $range['maximum'],
            'multipleOf' => $node->get('multipleOf')->numberOrNull(),
            'exclusiveMinimum' => $range['exclusiveMinimum'],
            'exclusiveMaximum' => $range['exclusiveMaximum'],
            'default' => $this->integerValue($node->get('default')),
            'example' => $this->integerValue($this->example($node, 'integer')),
        ];
    }

    /**
     * @return array{
     *     minimum: null|float|int,
     *     maximum: null|float|int,
     *     multipleOf: null|float|int,
     *     exclusiveMinimum: bool,
     *     exclusiveMaximum: bool,
     *     default: ?NumberValue,
     *     example: ?NumberValue,
     * }
     */
    private function numberOnly(Node $node): array
    {
        $range = $this->range($node);

        return [
            'minimum' => $range['minimum'],
            'maximum' => $range['maximum'],
            'multipleOf' => $node->get('multipleOf')->numberOrNull(),
            'exclusiveMinimum' => $range['exclusiveMinimum'],
            'exclusiveMaximum' => $range['exclusiveMaximum'],
            'default' => $this->numberValue($node->get('default')),
            'example' => $this->numberValue($this->example($node, 'number')),
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
            'example' => $this->arrayValue($this->example($node, 'array')),
        ];
    }

    /**
     * @return array{
     *     properties: ?Properties,
     *     required: ?Strings,
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
     * }
     */
    private function objectOnly(Node $node): array
    {
        $required = $node->get('required')->strings();
        $properties = [];

        foreach ($node->get('properties')->map() as $name => $item) {
            $properties[$name] = new Property(
                schema: $this->read($item),
                required: in_array((string) $name, $required, true),
            );
        }

        // обязательные имена без описания в properties; повторы ничего не меняют
        $undeclared = array_values(array_unique(array_filter(
            $required,
            static fn (string $name): bool => !array_key_exists($name, $properties),
        )));

        $patternProperties = [];

        foreach ($node->get('patternProperties')->map() as $pattern => $item) {
            $patternProperties[$pattern] = $this->read($item);
        }

        $dependentRequired = [];

        foreach ($node->get('dependentRequired')->map() as $name => $item) {
            $dependentRequired[$name] = new Strings(...$item->strings());
        }

        return [
            'properties' => $properties === [] ? null : Properties::fromArray($properties),
            'required' => $undeclared === [] ? null : new Strings(...$undeclared),
            'minProperties' => $this->nonNegative($node->get('minProperties')) ?? 0,
            'maxProperties' => $this->nonNegative($node->get('maxProperties')),
            // по умолчанию дополнительные свойства разрешены, и параметр не nullable
            'additionalProperties' => $this->readSchemaOrBool($node->get('additionalProperties')) ?? true,
            'patternProperties' => $patternProperties === [] ? null : PatternProperties::fromArray($patternProperties),
            'propertyNames' => $node->has('propertyNames') ? $this->read($node->get('propertyNames')) : null,
            'dependentRequired' => $dependentRequired === [] ? null : DependentRequired::fromArray($dependentRequired),
            'dependentSchemas' => $this->readSchemas($node->get('dependentSchemas')),
            'unevaluatedProperties' => $this->readSchemaOrBool($node->get('unevaluatedProperties')),
            'default' => $this->objectValue($node->get('default')),
            'example' => $this->objectValue($this->example($node, 'object')),
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

        return $items === [] ? null : UntypedSchemas::fromArray($items);
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

        return $items === [] ? null : Vocabularies::fromArray($items);
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
            mapping: $mapping === [] ? null : UntypedSchemas::fromArray($mapping),
            extensions: $node->extensions(),
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
            extensions: $node->extensions(),
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
}
