<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Abstract;

use EugeneErg\OpenApi\Exceptions\InvalidSchemaOpenapiException;
use EugeneErg\OpenApi\Extensions;
use EugeneErg\OpenApi\ExternalDocs;
use EugeneErg\OpenApi\Process;
use EugeneErg\OpenApi\Serialization\Structure;
use stdClass;

use function count;
use function sprintf;

/**
 * A schema with a closed set of values: `enum`, or `const` for a single one.
 *
 * Why this is a kind of schema of its own rather than an `enum` parameter on an ordinary
 * one. Once the values are listed, any asserting keyword (minLength, pattern, minimum,
 * items, properties, allOf, not and so on) either holds for every value and changes
 * nothing, or excludes some of them, and then it is a mistake. There is no third case, so
 * those parameters are absent here altogether: the useless cannot be written and nothing
 * useful is lost. What is left are the annotations — what changes the meaning of the
 * document rather than the set of permitted values. `example` is gone for the same
 * reason: beside a list of values an example adds nothing.
 *
 * When an enum really has to be combined with composition, `allOf` expresses it, and that
 * spelling is equivalent.
 *
 * As with nullable in general, the output depends on the version: a single value prints
 * as `const` in 3.1 and as a one-member `enum` in 3.0, and nullable adds `null` to the
 * list itself — without that, 3.0.3 does not permit null.
 */
abstract readonly class AbstractEnumSchema extends AbstractSchema
{
    public function __construct(
        public AbstractValues $enums,
        ?string $type,
        ?string $format = null,
        ?string $title = null,
        ?string $description = null,
        bool $nullable = false,
        ?Access $access = null,
        bool $deprecated = false,
        ?ExternalDocs $externalDocs = null,
        ?Xml $xml = null,
        ?AbstractValue $default = null,
        ?Resource $resource = null,
        ?Extensions $extensions = null,
    ) {
        if ($enums->items === []) {
            throw new InvalidSchemaOpenapiException(
                'Enum must list at least one value; a schema that accepts nothing is written as not: {}.',
            );
        }

        $known = [];

        foreach ($enums->items as $value) {
            if ($value === null) {
                throw new InvalidSchemaOpenapiException(
                    'Do not list null among enum values: set nullable: true, the package adds null itself.',
                );
            }

            $key = JsonValue::key($value);

            if (isset($known[$key])) {
                throw new InvalidSchemaOpenapiException(sprintf('Enum lists %s more than once.', self::show($value)));
            }

            $known[$key] = true;
        }

        if ($default !== null && $default->value === null && !$nullable) {
            throw new InvalidSchemaOpenapiException('Default null requires nullable: true.');
        }

        if ($default !== null && $default->value !== null) {
            if (!isset($known[JsonValue::key($default->value)])) {
                throw new InvalidSchemaOpenapiException(sprintf(
                    'Default %s is not one of the enum values.',
                    self::show($default->value),
                ));
            }
        }

        parent::__construct(
            type: $type,
            format: $format,
            title: $title,
            description: $description,
            nullable: $nullable,
            access: $access,
            deprecated: $deprecated,
            externalDocs: $externalDocs,
            xml: $xml,
            default: $default,
            resource: $resource,
            extensions: $extensions,
        );

        // after the parent: the check looks at format, and format is declared there
        foreach ($enums->items as $value) {
            $this->assertValue($value);
        }
    }

    public function toObject(Process $process): stdClass
    {
        $result = Structure::vars(parent::toObject($process));

        // on a schema without a type the 3.0 nullable flag does not apply: null lives in the list alone
        if ($this->type === null) {
            unset($result['nullable']);
        }

        $values = [];

        foreach ($this->enums->items as $item) {
            $values[] = $item instanceof AbstractValues ? $item->toNative($process) : $item;
        }

        if ($this->nullable) {
            $values[] = null;
        }

        if (count($values) === 1 && $process->version()->isV31()) {
            $result['const'] = $values[0];
        } else {
            $result['enum'] = $values;
        }

        return (object) $this->extensions->appendTo($result);
    }

    /**
     * Checks a value against the constraints an enum keeps, such as format.
     */
    protected function assertValue(AbstractValues|bool|float|int|string $value): void
    {
    }

    protected static function show(mixed $value): string
    {
        return JsonValue::key($value) === JsonValue::key(null)
            ? 'null'
            : (string) json_encode($value instanceof AbstractValues ? $value->items : $value);
    }
}
