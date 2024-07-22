<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Integer;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractConditionSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Access;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Xml;
use EugeneErg\OpenApi\ExternalDocs;
use EugeneErg\OpenApi\Process;
use stdClass;

final readonly class Schema extends AbstractConditionSchema
{
    public function __construct(
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
        public ?int $minimum = null,
        public ?int $maximum = null,
        public bool $exclusiveMinimum = false,
        public bool $exclusiveMaximum = false,
        public ?int $multipleOf = null,
        public ?Format $format = null,
    ) {
        parent::__construct(
            'integer',
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
        $result = (array) parent::toObject($process);

        if ($this->minimum !== null) {
            $result['minimum'] = $this->minimum;
        }

        if ($this->maximum !== null) {
            $result['maximum'] = $this->maximum;
        }

        if ($this->exclusiveMinimum !== false) {
            $result['exclusiveMinimum'] = $this->exclusiveMinimum;
        }

        if ($this->exclusiveMaximum !== false) {
            $result['exclusiveMaximum'] = $this->exclusiveMaximum;
        }

        if ($this->multipleOf !== null) {
            $result['multipleOf'] = $this->multipleOf;
        }

        if ($this->format !== null) {
            $result['format'] = $this->format->value;
        }

        return (object) $result;
    }
}
