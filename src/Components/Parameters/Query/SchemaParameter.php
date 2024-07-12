<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Parameters\Query;

use EugeneErg\OpenApi\Components;
use EugeneErg\OpenApi\Components\Parameters\Abstract\AbstractSchemaParameter;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValue;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValues;
use EugeneErg\OpenApi\Process;
use stdClass;

final readonly class SchemaParameter extends AbstractSchemaParameter
{
    public function __construct(
        AbstractSchema $schema,
        ?bool $explode = null,
        public bool $allowEmptyValue = false,
        public bool $allowReserved = false,
        null|AbstractValue|AbstractValues $examples = null,
        ?string $description = null,
        bool $required = false,
        bool $deprecated = false,
        public Style $style = Style::Form,
    ) {
        parent::__construct(Components\Parameters\In::Query, $schema, $explode ?? $style === Style::Form, $description, $required, $deprecated, $examples);
    }

    public function toObject(Process $process): stdClass
    {
        $result = array_merge((array) parent::toObject($process), ['style' => $this->style->value]);

        if ($this->allowEmptyValue) {
            $result['allowEmptyValue'] = $this->allowEmptyValue;
        }

        if ($this->allowReserved) {
            $result['allowReserved'] = $this->allowReserved;
        }

        return (object) $result;
    }
}
