<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\String;

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
        public int $minLength = 0,
        public ?int $maxLength = null,
        public ?string $pattern = null,
        public ?Format $format = null,
    ) {
        parent::__construct(
            'string',
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

        if ($this->minLength !== 0) {
            $result['minLength'] = $this->minLength;
        }

        if ($this->maxLength !== null) {
            $result['maxLength'] = $this->maxLength;
        }

        if ($this->pattern !== null) {
            $result['pattern'] = $this->pattern;
        }

        if ($this->format !== null) {
            $result['format'] = $this->format;
        }

        return (object) $result;
    }
}
