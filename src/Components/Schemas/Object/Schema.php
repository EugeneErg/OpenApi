<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Object;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractConditionSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchemas;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValue;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValues;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Access;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Bounds;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Discriminator;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Vocabularies;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Xml;
use EugeneErg\OpenApi\Components\Schemas\Untyped\Schemas as UntypedSchemas;
use EugeneErg\OpenApi\ExternalDocs;
use EugeneErg\OpenApi\Process;
use stdClass;

final readonly class Schema extends AbstractConditionSchema
{
    use Bounds;

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
        public null|AbstractSchema|bool $unevaluatedProperties = null,
        ?Discriminator $discriminator = null,
        public int $minProperties = 0,
        public ?int $maxProperties = null,
        public AbstractSchema|bool $additionalProperties = true,
        ?AbstractValue $const = null,
        ?AbstractValues $examples = null,
        ?string $comment = null,
        ?AbstractSchemas $defs = null,
        ?string $id = null,
        ?string $anchor = null,
        ?string $dynamicAnchor = null,
        ?AbstractSchema $dynamicRef = null,
        ?Vocabularies $vocabulary = null,
        ?AbstractSchema $if = null,
        ?AbstractSchema $then = null,
        ?AbstractSchema $else = null,
    ) {
        $this->properties = $properties ?? new Properties();
        $this->patternProperties = $patternProperties ?? new PatternProperties();
        $this->dependentRequired = $dependentRequired ?? new DependentRequired();
        $this->dependentSchemas = $dependentSchemas ?? new UntypedSchemas();

        self::assertRange('Object schema property count', $this->minProperties, $this->maxProperties);

        parent::__construct(
            'object',
            $title,
            $description,
            $nullable,
            $access,
            $deprecated,
            $externalDocs,
            $xml,
            $default,
            $anyOf,
            $allOf,
            $oneOf,
            $not,
            $example,
            $discriminator,
            $const,
            $examples,
            $comment,
            $defs,
            $id,
            $anchor,
            $dynamicAnchor,
            $dynamicRef,
            $vocabulary,
            $if,
            $then,
            $else,
        );
    }

    public function toObject(Process $process): stdClass
    {
        $required = [];
        $properties = [];

        foreach ($this->properties->items as $name => $property) {
            if ($property->required) {
                $required[] = $name;
            }

            $properties[$name] = $property->schema;
        }

        $result = get_object_vars(parent::toObject($process));

        if ($properties !== []) {
            $result['properties'] = (new UntypedSchemas(...$properties))->toObject($process);
        }

        if ($required !== []) {
            $result['required'] = $required;
        }

        if ($this->minProperties > 0) {
            $result['minProperties'] = $this->minProperties;
        }

        if ($this->maxProperties !== null) {
            $result['maxProperties'] = $this->maxProperties;
        }

        if ($this->additionalProperties !== true) {
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

        return (object) $result;
    }
}
