<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Object;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractConditionSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchemas;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValues;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Access;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Bounds;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Discriminator;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Resource;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Xml;
use EugeneErg\OpenApi\Components\Schemas\String\Strings;
use EugeneErg\OpenApi\Components\Schemas\Untyped\Schemas as UntypedSchemas;
use EugeneErg\OpenApi\Exceptions\InvalidSchemaOpenapiException;
use EugeneErg\OpenApi\Exceptions\Place;
use EugeneErg\OpenApi\Extensions;
use EugeneErg\OpenApi\ExternalDocs;
use EugeneErg\OpenApi\Process;
use EugeneErg\OpenApi\Serialization\Structure;
use stdClass;

use function array_key_exists;
use function sprintf;

final readonly class Schema extends AbstractConditionSchema
{
    use Bounds;
    public Strings $required;

    public PatternProperties $patternProperties;
    public DependentRequired $dependentRequired;
    public UntypedSchemas $dependentSchemas;

    /**
     * @param int<0, max> $minProperties
     * @param null|int<0, max> $maxProperties
     */
    public Properties $properties;

    public function __construct(
        ?Properties $properties = null,
        ?string $title = null,
        ?string $description = null,
        ?string $format = null,
        bool $nullable = false,
        ?Access $access = null,
        bool $deprecated = false,
        ?ExternalDocs $externalDocs = null,
        ?Xml $xml = null,
        ?Value $default = null,
        ?AbstractSchemas $anyOf = null,
        ?AbstractSchemas $allOf = null,
        ?AbstractSchemas $oneOf = null,
        ?AbstractSchema $not = null,
        ?Value $example = null,
        ?PatternProperties $patternProperties = null,
        public ?AbstractSchema $propertyNames = null,
        ?DependentRequired $dependentRequired = null,
        ?UntypedSchemas $dependentSchemas = null,
        public AbstractSchema|bool|null $unevaluatedProperties = null,
        ?Discriminator $discriminator = null,
        public int $minProperties = 0,
        public ?int $maxProperties = null,
        public AbstractSchema|bool $additionalProperties = true,
        ?AbstractValues $examples = null,
        ?Resource $resource = null,
        /**
         * A schema may describe a shape without declaring type: the specification allows
         * that, and reading somebody else's document must not lose such a shape.
         */
        bool $declareType = true,
        ?AbstractSchema $if = null,
        ?AbstractSchema $then = null,
        ?AbstractSchema $else = null,
        /**
         * Required names that properties does not describe: their shape comes from
         * additionalProperties, patternProperties or a composition. A described property
         * is made required through Property(required: true) — there is no second way.
         */
        ?Strings $required = null,
        ?Extensions $extensions = null,
    ) {
        $this->properties = $properties ?? new Properties();
        $this->required = $required ?? new Strings();
        $this->patternProperties = $patternProperties ?? new PatternProperties();
        $this->dependentRequired = $dependentRequired ?? new DependentRequired();
        $this->dependentSchemas = $dependentSchemas ?? new UntypedSchemas();

        self::assertRange('Object schema property count', $this->minProperties, $this->maxProperties);
        $this->assertRequired();

        parent::__construct(
            type: $declareType ? 'object' : null,
            format: $format,
            title: $title,
            description: $description,
            nullable: $nullable,
            access: $access,
            deprecated: $deprecated,
            externalDocs: $externalDocs,
            xml: $xml,
            default: $default,
            anyOf: $anyOf,
            allOf: $allOf,
            oneOf: $oneOf,
            not: $not,
            example: $example,
            discriminator: $discriminator,
            examples: $examples,
            if: $if,
            then: $then,
            else: $else,
            resource: $resource,
            extensions: $extensions,
        );
    }

    public function toObject(Process $process): stdClass
    {
        $required = [];
        $properties = [];

        foreach ($this->properties->items as $name => $property) {
            if ($property->required) {
                $required[] = (string) $name;
            }

            $properties[$name] = $property->schema;
        }

        $result = Structure::vars(parent::toObject($process));

        if ($properties !== []) {
            $result['properties'] = Place::in(
                static fn (): stdClass => UntypedSchemas::fromArray($properties)->toObject($process),
                'properties',
            );
        }

        $required = [...$required, ...array_map('strval', $this->required->items)];

        if ($required !== []) {
            $result['required'] = $required;
        }

        if ($this->minProperties > 0 || $process->verbose) {
            $result['minProperties'] = $this->minProperties;
        }

        if ($this->maxProperties !== null) {
            $result['maxProperties'] = $this->maxProperties;
        }

        if ($this->additionalProperties !== true || $process->verbose) {
            $result['additionalProperties'] = $this->additionalProperties instanceof AbstractSchema
                ? ($process->findSchema($this->additionalProperties) ?? $this->additionalProperties->toObject($process))
                : $this->additionalProperties;
        }

        if ($this->patternProperties->items !== []) {
            $process->assertV31('"patternProperties"');
            $result['patternProperties'] = $this->patternProperties->toObject($process);
        }

        if ($this->propertyNames !== null) {
            $process->assertV31('"propertyNames"');
            $result['propertyNames'] = self::nested($this->propertyNames, $process);
        }

        if ($this->dependentRequired->items !== []) {
            $process->assertV31('"dependentRequired"');
            $result['dependentRequired'] = $this->dependentRequired->toObject();
        }

        if ($this->dependentSchemas->items !== []) {
            $process->assertV31('"dependentSchemas"');
            $result['dependentSchemas'] = $this->dependentSchemas->toObject($process);
        }

        if ($this->unevaluatedProperties !== null) {
            $process->assertV31('"unevaluatedProperties"');
            $result['unevaluatedProperties'] = $this->unevaluatedProperties instanceof AbstractSchema
                ? self::nested($this->unevaluatedProperties, $process)
                : $this->unevaluatedProperties;
        }

        return (object) $this->extensions->appendTo($result);
    }

    private function assertRequired(): void
    {
        $seen = [];

        foreach ($this->required->items as $name) {
            $name = (string) $name;

            if (isset($seen[$name])) {
                throw new InvalidSchemaOpenapiException(sprintf('Required name "%s" is listed more than once.', $name));
            }

            $seen[$name] = true;

            if (array_key_exists($name, $this->properties->items)) {
                throw new InvalidSchemaOpenapiException(sprintf(
                    'Property "%s" is described in properties: make it required with Property(required: true).',
                    $name,
                ));
            }

            if ($this->additionalProperties === false && !$this->matchesPattern($name)) {
                throw new InvalidSchemaOpenapiException(sprintf(
                    'Required name "%s" can never be present: additionalProperties is false '
                    . 'and neither properties nor patternProperties allow it.',
                    $name,
                ));
            }
        }
    }

    /**
     * true when the name matches some patternProperties — or when that cannot be checked.
     */
    private function matchesPattern(string $name): bool
    {
        foreach (array_keys($this->patternProperties->items) as $pattern) {
            set_error_handler(static fn (): bool => true);

            try {
                $matched = preg_match("\x01" . $pattern . "\x01u", $name);
            } finally {
                restore_error_handler();
            }

            if ($matched !== 0) {
                return true;
            }
        }

        return false;
    }
}
