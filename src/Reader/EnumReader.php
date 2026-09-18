<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Reader;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValues;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Access;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Discriminator;
use EugeneErg\OpenApi\Components\Schemas\Abstract\JsonValue;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Resource;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Xml;
use EugeneErg\OpenApi\Components\Schemas\Array\Arrays;
use EugeneErg\OpenApi\Components\Schemas\Array\EnumSchema as ArrayEnumSchema;
use EugeneErg\OpenApi\Components\Schemas\Boolean\EnumSchema as BooleanEnumSchema;
use EugeneErg\OpenApi\Components\Schemas\Boolean\Schema as BooleanSchema;
use EugeneErg\OpenApi\Components\Schemas\Integer\EnumSchema as IntegerEnumSchema;
use EugeneErg\OpenApi\Components\Schemas\Integer\Integers;
use EugeneErg\OpenApi\Components\Schemas\Number\EnumSchema as NumberEnumSchema;
use EugeneErg\OpenApi\Components\Schemas\Number\Numbers;
use EugeneErg\OpenApi\Components\Schemas\Object\EnumSchema as ObjectEnumSchema;
use EugeneErg\OpenApi\Components\Schemas\Object\Objects;
use EugeneErg\OpenApi\Components\Schemas\String\EnumSchema as StringEnumSchema;
use EugeneErg\OpenApi\Components\Schemas\String\Strings;
use EugeneErg\OpenApi\Components\Schemas\Untyped\EnumSchema as UntypedEnumSchema;
use EugeneErg\OpenApi\Components\Schemas\Untyped\Schema as UntypedSchema;
use EugeneErg\OpenApi\Components\Schemas\Untyped\Schemas as UntypedSchemas;
use EugeneErg\OpenApi\Components\Schemas\Untyped\Values;
use EugeneErg\OpenApi\Exceptions\InvalidDocumentOpenapiException;
use EugeneErg\OpenApi\Exceptions\InvalidSchemaOpenapiException;
use EugeneErg\OpenApi\Extensions;
use EugeneErg\OpenApi\ExternalDocs;
use stdClass;

use function count;
use function in_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function sprintf;

/**
 * Reads enums.
 *
 * Kept apart from the schema reader because an enum is read differently: what is
 * parsed there is not a set of keywords but a set of values, and they decide which of
 * the neighbouring keywords mean anything at all. A keyword beside `enum` either holds
 * for every value (and then says nothing), or excludes some of them (and then the
 * document contradicts itself), or applies a subschema — and then the schema is
 * rewritten as an `allOf` of the applicator and the enum itself.
 */
final readonly class EnumReader
{
    /**
     * The keywords an enum keeps: the values themselves, the type, nullable and the
     * annotations. Everything else either asserts nothing or moves into an allOf.
     */
    private const array KEPT = [
        'enum', 'const', 'type', 'nullable', 'example', 'examples', 'default',
        'title', 'description', 'readOnly', 'writeOnly', 'deprecated', 'externalDocs', 'xml',
        '$comment', '$defs', '$id', '$anchor', '$dynamicAnchor', '$vocabulary', '$schema',
    ];

    /**
     * The annotations about the contents of a string: by JSON Schema they assert nothing
     * at all, so they neither exclude a value nor need a subschema of their own. A string
     * enum keeps them, and an enum of any other kind has nowhere to keep them — there
     * they mean nothing, like any keyword of another type.
     */
    private const array CONTENT = ['contentEncoding', 'contentMediaType', 'contentSchema'];

    public function __construct(
        private SchemaReader $schemas,
        private ValueReader $values,
    ) {
    }

    /**
     * A schema no value fits: the remaining keywords plus `not: {}`.
     */
    public function readNothing(Node $node): AbstractSchema
    {
        $rest = new stdClass();

        foreach (JsonValue::members($node->value) as $keyword => $argument) {
            if ($keyword !== 'enum') {
                $rest->{$keyword} = $argument;
            }
        }

        $rest->not = new stdClass();

        return $this->schemas->readSchema($node->rewritten($rest));
    }

    /**
     * An enum (`enum` and/or `const`).
     *
     * An enum schema keeps only its annotations (see AbstractEnumSchema), so the rest of
     * the keywords are read like this:
     * - `example`/`examples` and keywords inapplicable to the kind of the values change
     *   nothing and are dropped;
     * - plain assertions (length, pattern, bounds, required and such) are checked against
     *   every value: if all of them pass, the keyword changes nothing and is dropped;
     *   otherwise the document contradicts itself — some values are unreachable;
     * - whatever cannot be checked that way (composition, properties, items and such) is
     *   kept without loss as the equivalent `allOf: [the rest of the schema, the enum]`.
     *
     * By the letter of 3.0.3 a `nullable: true` without null among the values allows
     * nothing, but that is written all the time and nullable is what it means. So it is
     * read by intent: on the way out null lands both in the flag and in the list, and the
     * document becomes unambiguous for any tool.
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
     *     resource: ?resource,
     *     if: ?AbstractSchema,
     *     then: ?AbstractSchema,
     *     else: ?AbstractSchema,
     *     extensions: ?Extensions,
     *     format: ?string
     * } $common
     */
    public function readEnum(Node $node, ?string $type, bool $nullable, array $common): AbstractSchema
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

        foreach (JsonValue::members($node->value) as $keyword => $unused) {
            $keyword = (string) $keyword;

            if (in_array($keyword, self::CONTENT, true)) {
                if ($type !== 'string') {
                    // read and dropped: strict reading complains about what was not read
                    $node->dropped($keyword);
                }

                continue;
            }

            if (!in_array($keyword, self::KEPT, true) && !$this->assertionHolds($node, $keyword, $type, $values)) {
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
            'resource' => $common['resource'],
            'extensions' => $common['extensions'],
            'format' => $common['format'],
        ];

        return $this->buildEnum($node, $type, $values, $annotations);
    }

    /**
     * @return list<Node> the values without repetitions; `const` together with `enum` is their intersection
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
     * true means the keyword does not change the set of values and can be dropped.
     *
     * @param list<Node> $values
     */
    private function assertionHolds(Node $node, string $keyword, ?string $type, array $values): bool
    {
        if (str_starts_with($keyword, 'x-')) {
            return true;
        }

        $applies = Keywords::KINDS[$keyword] ?? null;

        // an unknown keyword: the package does not model it in ordinary schemas either
        if ($applies === null) {
            return !Keywords::isApplicator($keyword);
        }

        $argument = $node->get($keyword);

        foreach ($values as $value) {
            $kind = JsonValue::kind($value->value);

            // a keyword acts only on values of its own kind
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
     * @return null|bool null means it cannot be checked
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
                return count(JsonValue::members($value)) >= $argument->int();

            case 'maxItems':
                return count(JsonValue::members($value)) <= $argument->int();

            case 'uniqueItems':
                if ($argument->value !== true) {
                    return true;
                }

                $keys = array_map(JsonValue::key(...), JsonValue::members($value));

                return count($keys) === count(array_unique($keys));

            case 'minProperties':
                return count(JsonValue::members($value)) >= $argument->int();

            case 'maxProperties':
                return count(JsonValue::members($value)) <= $argument->int();

            case 'required':
                return array_diff($argument->strings(), array_map('strval', array_keys(JsonValue::members($value)))) === [];
        }

        return null;
    }

    /**
     * The equivalent lossless form: the rest of the schema and the enum joined by allOf.
     *
     * @param list<Node> $values
     */
    private function splitEnum(Node $node, ?string $type, bool $nullable, array $values): AbstractSchema
    {
        $rest = new stdClass();

        foreach (JsonValue::members($node->value) as $keyword => $argument) {
            if ($keyword !== 'enum' && $keyword !== 'const') {
                $rest->{$keyword} = $argument;
            }
        }

        return new UntypedSchema(allOf: new UntypedSchemas(
            $this->schemas->readSchema($node->rewritten($rest)),
            $this->buildEnum($node, $type, $values, [
                'title' => null,
                'description' => null,
                'nullable' => $nullable,
                'access' => null,
                'deprecated' => false,
                'externalDocs' => null,
                'xml' => null,
                'resource' => null,
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
     *     resource: ?resource,
     *     extensions: ?Extensions,
     *     format: ?string
     * } $annotations
     */
    private function buildEnum(Node $node, ?string $type, array $values, array $annotations, bool $withDefault = true): AbstractSchema
    {
        $default = $withDefault ? $node->get('default') : new Node(null, $node->path, present: false);

        // beside an enum an example illustrates nothing: the values are the list
        $node->dropped('example');

        try {
            switch ($type) {
                case 'string':
                    $strings = new Strings(...array_map(static fn (Node $value): string => $value->string(), $values));

                    return new StringEnumSchema(
                        ...$annotations,
                        enums: $strings,
                        default: $this->values->stringValue($default),
                        contentEncoding: $node->get('contentEncoding')->stringOrNull(),
                        contentMediaType: $node->get('contentMediaType')->stringOrNull(),
                        contentSchema: $node->has('contentSchema') ? $this->schemas->read($node->get('contentSchema')) : null,
                    );

                case 'integer':
                    $integers = new Integers(...array_map(ValueReader::integer(...), $values));

                    return new IntegerEnumSchema(
                        ...$annotations,
                        enums: $integers,
                        default: $this->values->integerValue($default),
                    );

                case 'number':
                    $numbers = new Numbers(...array_map(ValueReader::number(...), $values));

                    return new NumberEnumSchema(
                        ...$annotations,
                        enums: $numbers,
                        default: $this->values->numberValue($default),
                    );

                case 'boolean':
                    $booleanDefault = $this->values->booleanValue($default);

                    // a list of both values restricts nothing
                    return count($values) === 2
                        ? new BooleanSchema(...$annotations, default: $booleanDefault)
                        : new BooleanEnumSchema(...$annotations, value: $values[0]->bool(), default: $booleanDefault);

                case 'array':
                    $arrays = new Arrays(...array_map($this->values->listOf(...), $values));

                    return new ArrayEnumSchema(...$annotations, enums: $arrays, default: $this->values->arrayValue($default));

                case 'object':
                    $objects = new Objects(...array_map($this->values->mapOf(...), $values));

                    return new ObjectEnumSchema(...$annotations, enums: $objects, default: $this->values->objectValue($default));

                default:
                    $mixed = new Values(...array_map($this->values->nativeOf(...), $values));
                    $mixedDefault = $this->values->untypedValue($default);

                    return new UntypedEnumSchema(...$annotations, enums: $mixed, default: $mixedDefault);
            }
        } catch (InvalidSchemaOpenapiException $exception) {
            throw self::contradiction($node, lcfirst(rtrim($exception->getMessage(), '.')));
        }
    }

    private static function contradiction(Node $node, string $reason): InvalidDocumentOpenapiException
    {
        return new InvalidDocumentOpenapiException(sprintf('%s: %s.', $node->path, $reason));
    }
}
