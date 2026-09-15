<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Examples;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValue;
use EugeneErg\OpenApi\Exceptions\InvalidArgumentOpenapiException;
use EugeneErg\OpenApi\Process;
use stdClass;

/**
 * Example Object.
 *
 * Отдельный объект, а не голое значение: во-первых, спецификация требует именно объект,
 * во-вторых, дедупликация в пакете работает по идентичности, а у скаляров её нет —
 * две одинаковые строки неразличимы, и ссылка уезжала бы не туда.
 */
final readonly class Example
{
    public function __construct(
        public ?AbstractValue $value = null,
        public ?string $summary = null,
        public ?string $description = null,
        public ?string $externalValue = null,
    ) {
        if ($value !== null && $externalValue !== null) {
            throw new InvalidArgumentOpenapiException(
                'Example cannot have both value and externalValue: they are mutually exclusive.',
            );
        }
    }

    public function toObject(Process $process): stdClass
    {
        $result = [];

        if ($this->summary !== null) {
            $result['summary'] = $this->summary;
        }

        if ($this->description !== null) {
            $result['description'] = $this->description;
        }

        if ($this->value !== null) {
            $result['value'] = $this->value->toNative($process);
        }

        if ($this->externalValue !== null) {
            $result['externalValue'] = $this->externalValue;
        }

        return (object) $result;
    }
}
