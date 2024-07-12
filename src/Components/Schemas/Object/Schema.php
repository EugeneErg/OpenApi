<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Object;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractConditionSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Access;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Xml;
use EugeneErg\OpenApi\Components\Schemas\Untyped\Schemas as UntypedSchemas;
use EugeneErg\OpenApi\ExternalDocs;
use EugeneErg\OpenApi\Process;
use stdClass;

final readonly class Schema extends AbstractConditionSchema
{
    public function __construct(
        public Properties $properties,
        ?string $title = null,
        ?string $description = null,
        bool $nullable = false,
        ?Access $access = null,
        bool $deprecated = false,
        ?ExternalDocs $externalDocs = null,
        ?Xml $xml = null,
        ?Value $default = null,
        ?Schemas $anyOf = null,
        ?Schemas $allOf = null,
        ?Schemas $oneOf = null,
        null|self|EnumSchema $not = null,
        null|Value $example = null,
        public int $minProperties = 0,
        public ?int $maxProperties = null,
        public bool|AbstractSchema $additionalProperties = true,
    ) {
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

        $result = array_merge((array) parent::toObject($process), [
            'properties' => (new UntypedSchemas(...$properties))->toObject($process),
        ]);

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
            $result['additionalProperties'] = $this->additionalProperties;
        }

        return (object) $result;
    }
}
