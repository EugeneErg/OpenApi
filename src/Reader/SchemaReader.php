<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Reader;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValue;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValues;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Access;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Discriminator;
use EugeneErg\OpenApi\Components\Schemas\Abstract\JsonValue;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Resource;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Vocabularies;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Xml;
use EugeneErg\OpenApi\Components\Schemas\Array\Schema as ArraySchema;
use EugeneErg\OpenApi\Components\Schemas\Array\Value as ArrayValue;
use EugeneErg\OpenApi\Components\Schemas\Boolean\Schema as BooleanSchema;
use EugeneErg\OpenApi\Components\Schemas\Boolean\Value as BooleanValue;
use EugeneErg\OpenApi\Components\Schemas\Integer\Schema as IntegerSchema;
use EugeneErg\OpenApi\Components\Schemas\Integer\Value as IntegerValue;
use EugeneErg\OpenApi\Components\Schemas\Null\Schema as NullSchema;
use EugeneErg\OpenApi\Components\Schemas\Number\Schema as NumberSchema;
use EugeneErg\OpenApi\Components\Schemas\Number\Value as NumberValue;
use EugeneErg\OpenApi\Components\Schemas\Object\DependentRequired;
use EugeneErg\OpenApi\Components\Schemas\Object\PatternProperties;
use EugeneErg\OpenApi\Components\Schemas\Object\Properties;
use EugeneErg\OpenApi\Components\Schemas\Object\Property;
use EugeneErg\OpenApi\Components\Schemas\Object\Schema as ObjectSchema;
use EugeneErg\OpenApi\Components\Schemas\Object\Value as ObjectValue;
use EugeneErg\OpenApi\Components\Schemas\String\Schema as StringSchema;
use EugeneErg\OpenApi\Components\Schemas\String\Strings;
use EugeneErg\OpenApi\Components\Schemas\String\Value as StringValue;
use EugeneErg\OpenApi\Components\Schemas\Untyped\Schema as UntypedSchema;
use EugeneErg\OpenApi\Components\Schemas\Untyped\Schemas as UntypedSchemas;
use EugeneErg\OpenApi\Exceptions\InvalidDocumentOpenapiException;
use EugeneErg\OpenApi\Extensions;
use EugeneErg\OpenApi\ExternalDocs;
use stdClass;

use function array_key_exists;
use function count;
use function in_array;
use function is_array;
use function is_bool;
use function is_string;
use function sprintf;

/**
 * Reads a Schema Object: picks the branch by type and reads the keywords of that branch.
 *
 * The model here is type-centric, so the branch follows `type`. An absent type, or the
 * array form of 3.1, is read into Untyped\Schema; `["string", "null"]` into a string
 * schema with nullable.
 *
 * Three parts that are read by other rules live beside it: `ValueReader` for values
 * (`default`, `example`, the members of an enum), `EnumReader` for enums, and `Keywords`
 * for which kind of value a keyword applies to.
 */
final readonly class SchemaReader
{
    private ValueReader $values;
    private EnumReader $enums;

    public function __construct(private Registry $registry)
    {
        $this->values = new ValueReader();
        $this->enums = new EnumReader($this, $this->values);
    }

    /**
     * Values — `default`, `example`, `examples` — are read by ValueReader; callers outside
     * (an Example Object, the parameters) ask through the schema.
     */
    public function readValue(Node $node, ?string $type = null): ?AbstractValue
    {
        return $this->values->readValue($node, $type);
    }

    public function readValues(Node $node): AbstractValues
    {
        return $this->values->readValues($node);
    }

    /**
     * The schema of a node. If the registry declares that node, the shared instance is
     * returned: otherwise a schema inside $defs would be built twice and a reference to it
     * would not find its target.
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
     * A component schema. Even when it is nothing but a reference to another schema — an
     * alias — it has to stay a separate object, or the two names merge into one.
     */
    public function readComponentSchema(Node $node): AbstractSchema
    {
        $schema = $this->readSchema($node);

        if ($node->has('$ref') && array_keys(JsonValue::members($node->value)) === ['$ref']) {
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
                array_keys(JsonValue::members($node->value)),
                static fn (int|string $keyword): bool => $keyword !== '$ref' && !str_starts_with((string) $keyword, 'x-'),
            );

            // in 3.0 the siblings of a $ref mean nothing
            if ($siblings === [] || !$this->registry->isV31()) {
                // read and dropped: in 3.0 this is a Reference Object, whose other fields
                // do not apply, and in 3.1 they "SHALL be ignored"
                $node->dropped(...array_map(strval(...), array_keys(JsonValue::members($node->value))));

                return $target;
            }

            // in 3.1 $ref is one of the keywords; the same schema without it is an allOf with the reference
            $rest = new stdClass();
            $rest->allOf = [(object) ['$ref' => $node->get('$ref')->string()]];

            foreach (JsonValue::members($node->value) as $keyword => $argument) {
                if ($keyword === 'allOf') {
                    $rest->allOf = [...$rest->allOf, ...(array) $argument];
                } elseif ($keyword !== '$ref') {
                    $rest->{$keyword} = $argument;
                }
            }

            return $this->readSchema($node->rewritten($rest));
        }

        [$declared, $nullableFlag] = $this->readType($node);
        $types = array_values(array_filter($declared, static fn (string $item): bool => $item !== 'null'));
        $nullable = $nullableFlag || $types !== $declared;

        // Schemas here are described per type, so a union of several types and a schema
        // of nothing but null are read separately.
        if ($declared !== [] && $types === []) {
            return new NullSchema(...$this->common($node, false));
        }

        if (count($types) > 1) {
            return $this->readUnion($node, $types, $nullable);
        }

        $type = $types[0] ?? null;
        $common = $this->common($node, $nullable);

        if ($node->has('enum') || $node->has('const')) {
            // JSON Schema allows an empty list: nothing fits it, and the equivalent
            // spelling of that is `not: {}`
            return $node->get('enum')->isPresent() && $node->get('enum')->list() === []
                ? $this->enums->readNothing($node)
                : $this->enums->readEnum($node, $type, $nullable, $common);
        }

        // the type may be absent while the assertions are there: then the branch follows
        // the keywords and no type is declared on the way out
        $declareType = $type !== null;

        if ($type === null) {
            $shapes = self::inferShapes($node);

            if (count($shapes) > 1) {
                return $this->readShapes($node, $shapes);
            }

            $type = $shapes[0] ?? null;
        }

        // a keyword about values of another type means nothing to this schema: it is read
        // and dropped, and strict reading has nothing to complain about
        $node->dropped(...self::inapplicable($type));

        return match ($type) {
            'string' => new StringSchema(...[...$common, ...$this->stringOnly($node), 'declareType' => $declareType]),
            'integer' => new IntegerSchema(...[...$common, ...$this->integerOnly($node), 'declareType' => $declareType]),
            'number' => new NumberSchema(...[...$common, ...$this->numberOnly($node), 'declareType' => $declareType]),
            'boolean' => new BooleanSchema(...[...$common, ...$this->booleanOnly($node)]),
            'array' => new ArraySchema(...[...$common, ...$this->arrayOnly($node), 'declareType' => $declareType]),
            'object' => new ObjectSchema(...[...$common, ...$this->objectOnly($node), 'declareType' => $declareType]),
            default => new UntypedSchema(...[
                ...$common,
                'default' => $this->values->untypedValue($node->get('default')),
                'example' => $this->values->untypedValue($node->get('example')),
            ]),
        };
    }

    /**
     * The branches of a schema without a type: the keywords that apply to values of one
     * kind decide them.
     *
     * `{"minLength": 3}` says "a string longer than two", and a value of any other type
     * fits such a schema: an assertion about strings does not apply to it. So no type is
     * declared here, and the keyword still has to reach the document.
     *
     * @return list<string> in a fixed order, so the output does not depend on the order of the keywords
     */
    private static function inferShapes(Node $node): array
    {
        $shapes = [];

        foreach (array_keys(JsonValue::members($node->value)) as $keyword) {
            $shape = Keywords::kindOf($keyword);

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
     * The keywords that apply to values of another kind. A schema without a type has
     * none of those: a value of any type fits it.
     *
     * @return list<string>
     */
    private static function inapplicable(?string $type): array
    {
        if ($type === null) {
            return [];
        }

        $kind = $type === 'integer' ? 'number' : $type;

        return array_values(array_filter(
            array_keys([...Keywords::KINDS, ...Keywords::TYPED]),
            static fn (string $keyword): bool => ($kinds = Keywords::kindOf($keyword)) !== null
                && $kinds !== $kind,
        ));
    }

    /**
     * A schema without a type that holds keywords about values of different kinds:
     * `{"minLength": 3, "minItems": 1}` is an `allOf` of an assertion about strings and an
     * assertion about arrays. One class cannot spell that: each describes values of its
     * own type.
     *
     * @param list<string> $shapes
     */
    private function readShapes(Node $node, array $shapes): AbstractSchema
    {
        $branches = [];
        $shared = new stdClass();

        foreach (JsonValue::members($node->value) as $keyword => $argument) {
            if (Keywords::kindOf($keyword) === null) {
                $shared->{$keyword} = $argument;
            }
        }

        foreach ($shapes as $shape) {
            $branch = new stdClass();

            foreach (JsonValue::members($node->value) as $keyword => $argument) {
                if (Keywords::kindOf($keyword) === $shape) {
                    $branch->{$keyword} = $argument;
                }
            }

            $branches[] = $this->readSchema($node->rewritten($branch));
        }

        if (JsonValue::members($shared) !== []) {
            array_unshift($branches, $this->readSchema($node->rewritten($shared)));
        }

        return new UntypedSchema(allOf: new UntypedSchemas(...$branches));
    }

    /**
     * A union of types: `{"type": ["string", "integer"], "maxLength": 5}` is an `anyOf` of
     * a string schema with maxLength and an integer schema. A keyword that applies to one
     * of the listed types goes into its branch; the rest stays shared.
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

        foreach (JsonValue::members($node->value) as $keyword => $argument) {
            $keyword = (string) $keyword;

            if ($keyword !== 'type' && $keyword !== 'nullable' && self::branchOf($keyword, $types) === null) {
                $shared->{$keyword} = $argument;
            }
        }

        foreach ($types as $type) {
            $branch = new stdClass();
            $branch->type = $type;

            foreach (JsonValue::members($node->value) as $keyword => $argument) {
                if (self::branchOf((string) $keyword, $types) === $type) {
                    $branch->{$keyword} = $argument;
                }
            }

            $branches[] = $type === 'null'
                ? new NullSchema()
                : $this->readSchema($node->rewritten($branch, $node->path . '/type/' . $type));
        }

        $union = count($branches) === 1
            ? $branches[0]
            : new UntypedSchema(anyOf: new UntypedSchemas(...$branches));

        return JsonValue::members($shared) === []
            ? $union
            : new UntypedSchema(allOf: new UntypedSchemas($this->readSchema($node->rewritten($shared)), $union));
    }

    /**
     * The type whose values a keyword applies to, if it is one of those listed.
     *
     * @param list<string> $types
     */
    private static function branchOf(string $keyword, array $types): ?string
    {
        $kind = Keywords::kindOf($keyword);

        if ($kind === null) {
            return null;
        }

        // integer is a special case of number, so a numeric keyword looks for both
        foreach ($kind === 'number' ? ['number', 'integer'] : [$kind] as $candidate) {
            if (in_array($candidate, $types, true)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * The declared types and the `nullable` flag.
     *
     * In 3.1 `type` may be an array, and a `null` in it is how nullable is expressed;
     * `type: "null"` with no other type means null is the only value allowed.
     *
     * @return array{list<string>, bool} the declared types as they are; the nullable flag
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
     * The fields every schema shares.
     *
     * The shape of the array is spelled out, or unpacking it into a constructor could not
     * be checked: PHPStan would see mixed in every parameter.
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
     *     resource: ?resource,
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
            'resource' => $this->readResource($node),
            'if' => $node->has('if') ? $this->read($node->get('if')) : null,
            'then' => $node->has('then') ? $this->read($node->get('then')) : null,
            'else' => $node->has('else') ? $this->read($node->get('else')) : null,
            'extensions' => $node->extensions(),
            // JSON Schema allows format on a value of any type
            'format' => $node->get('format')->stringOrNull(),
        ];
    }

    /**
     * @return array{default: ?BooleanValue, example: ?BooleanValue}
     */
    private function booleanOnly(Node $node): array
    {
        return [
            'default' => $this->values->booleanValue($node->get('default')),
            'example' => $this->values->booleanValue($this->values->example($node, 'boolean')),
        ];
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
            'default' => $this->values->stringValue($node->get('default')),
            'example' => $this->values->stringValue($this->values->example($node, 'string')),
        ];
    }

    /**
     * 3.0 puts a boolean flag beside minimum; 3.1 puts the number itself in its place.
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
            'default' => $this->values->integerValue($node->get('default')),
            'example' => $this->values->integerValue($this->values->example($node, 'integer')),
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
            'default' => $this->values->numberValue($node->get('default')),
            'example' => $this->values->numberValue($this->values->example($node, 'number')),
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
            'default' => $this->values->arrayValue($node->get('default')),
            'example' => $this->values->arrayValue($this->values->example($node, 'array')),
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

        // required names with no description in properties; repetitions change nothing
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
            // additional properties are allowed by default, and the parameter is not nullable
            'additionalProperties' => $this->readSchemaOrBool($node->get('additionalProperties')) ?? true,
            'patternProperties' => $patternProperties === [] ? null : PatternProperties::fromArray($patternProperties),
            'propertyNames' => $node->has('propertyNames') ? $this->read($node->get('propertyNames')) : null,
            'dependentRequired' => $dependentRequired === [] ? null : DependentRequired::fromArray($dependentRequired),
            'dependentSchemas' => $this->readSchemas($node->get('dependentSchemas')),
            'unevaluatedProperties' => $this->readSchemaOrBool($node->get('unevaluatedProperties')),
            'default' => $this->values->objectValue($node->get('default')),
            'example' => $this->values->objectValue($this->values->example($node, 'object')),
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

        // "#anchor" names an anchor of this document, "other.yaml#anchor" one of a
        // neighbouring file: a dynamic anchor is found by name, and the name is looked
        // for in the file the reference points at
        $position = strpos($ref, '#');
        $file = $position === false ? '' : substr($ref, 0, $position);
        $anchor = $position === false ? $ref : substr($ref, $position + 1);

        if ($file !== '' && !isset($this->registry->documents()[$file])) {
            throw new InvalidDocumentOpenapiException(sprintf(
                '%s: "$dynamicRef" points at file "%s", which was not passed to the reader.',
                $node->get('$dynamicRef')->path,
                $file,
            ));
        }

        foreach ($this->registry->pointers(($file === '' ? $this->registry->currentFile() : $file) . '#') as $pointer) {
            $candidate = $this->registry->node($pointer);

            if ($candidate?->get('$dynamicAnchor')->stringOrNull() === $anchor) {
                return $this->registry->resolveSchema($pointer);
            }
        }

        throw $node->get('$dynamicRef')->unexpected(sprintf('a schema declaring $dynamicAnchor "%s"', $anchor));
    }

    /**
     * The schema as a resource: the core vocabulary is read together because it is
     * declared together. An empty resource means there is none.
     */
    private function readResource(Node $node): ?Resource
    {
        $resource = new Resource(
            id: $node->get('$id')->stringOrNull(),
            schema: $node->get('$schema')->stringOrNull(),
            vocabulary: $this->readVocabulary($node->get('$vocabulary')),
            anchor: $node->get('$anchor')->stringOrNull(),
            dynamicAnchor: $node->get('$dynamicAnchor')->stringOrNull(),
            dynamicRef: $this->readDynamicRef($node),
            defs: $this->readSchemas($node->get('$defs')),
            comment: $node->get('$comment')->stringOrNull(),
        );

        return $resource->isEmpty() ? null : $resource;
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
}
