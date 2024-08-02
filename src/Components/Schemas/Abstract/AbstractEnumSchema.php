<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Abstract;

use EugeneErg\OpenApi\Components\Schemas\Object\OpenapiObject;
use EugeneErg\OpenApi\ExternalDocs;
use EugeneErg\OpenApi\Process;
use stdClass;

abstract readonly class AbstractEnumSchema extends AbstractSchema
{
    public function __construct(
        public AbstractValues $enums,
        ?string $title = null,
        ?string $description = null,
        bool $nullable = false,
        ?Access $access = null,
        bool $deprecated = false,
        ?ExternalDocs $externalDocs = null,
        ?Xml $xml = null,
        ?AbstractValue $default = null,
    ) {
        parent::__construct(
            $this->getType($enums, $default),
            $title,
            $description,
            $nullable,
            $access,
            $deprecated,
            $externalDocs,
            $xml,
            $default,
        );
    }

    public function toObject(Process $process): stdClass
    {
        $result = parent::toObject($process);

        $result->enum = $this->enums->toNative($process);

        return $result;
    }

    private function getType(AbstractValues $enums, ?AbstractValue $default): ?string
    {
        if ($enums->items === [] && $default === null) {
            return null;
        }

        $firstValue = $default ?? $enums->items[array_key_first($enums->items)];

        if ($firstValue instanceof OpenapiObject) {
            return 'object';
        }

        if ($firstValue instanceof AbstractValues) {
            return 'array';
        }

        $baseType = gettype($firstValue);

        return $baseType === 'NULL' ? 'null' : $baseType;
    }
}
