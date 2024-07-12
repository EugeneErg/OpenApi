<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Abstract;

use EugeneErg\OpenApi\Components\Schemas\Untyped\Schemas;
use EugeneErg\OpenApi\ExternalDocs;
use EugeneErg\OpenApi\Process;
use stdClass;

abstract readonly class AbstractConditionSchema extends AbstractSchema
{
    public AbstractSchemas $anyOf;
    public AbstractSchemas $allOf;
    public AbstractSchemas $oneOf;

    public function __construct(
        ?string $type,
        ?string $title = null,
        ?string $description = null,
        bool $nullable = false,
        ?Access $access = null,
        bool $deprecated = false,
        ?ExternalDocs $externalDocs = null,
        ?Xml $xml = null,
        ?AbstractValue $default = null,
        ?AbstractSchemas $anyOf = null,
        ?AbstractSchemas $allOf = null,
        ?AbstractSchemas $oneOf = null,
        public ?AbstractSchema $not = null,
        public null|AbstractValue $example = null,
    ) {
        parent::__construct($type, $title, $description, $nullable, $access, $deprecated, $externalDocs, $xml, $default);
        $this->anyOf = $anyOf ?? new Schemas();
        $this->allOf = $allOf ?? new Schemas();
        $this->oneOf = $oneOf ?? new Schemas();
    }

    public function toObject(Process $process): stdClass
    {
        $result = (array) parent::toObject($process);

        if ($this->anyOf->items !== []) {
            $result['anyOf'] = $this->anyOf->toArray($process);
        }

        if ($this->allOf->items !== []) {
            $result['allOf'] = $this->allOf->toArray($process);
        }

        if ($this->oneOf->items !== []) {
            $result['oneOf'] = $this->oneOf->toArray($process);
        }

        if ($this->not !== null) {
            $result['not'] = $this->not->toObject($process);
        }

        if ($this->example !== null) {
            $result['example'] = $this->example->toNative($process);
        }

        return (object) $result;
    }
}
