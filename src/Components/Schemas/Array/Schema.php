<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Array;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractConditionSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Access;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Xml;
use EugeneErg\OpenApi\ExternalDocs;
use EugeneErg\OpenApi\Process;
use stdClass;

final readonly class Schema extends AbstractConditionSchema
{
    public function __construct(
        public AbstractSchema $items,
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
        public int $minItems = 0,
        public ?int $maxItems = null,
        public bool $uniqueItems = false,
    ) {
        parent::__construct(
            'array',
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
        $result = array_merge((array) parent::toObject($process), [
            'items' => $process->findSchema($this->items) ?? $this->items->toObject($process),
        ]);

        if ($this->minItems > 0) {
            $result['minItems'] = $this->minItems;
        }

        if ($this->maxItems !== null) {
            $result['maxItems'] = $this->maxItems;
        }

        if ($this->uniqueItems) {
            $result['uniqueItems'] = $this->uniqueItems;
        }

        return (object) $result;
    }
}
