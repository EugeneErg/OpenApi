<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Examples;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValue;
use EugeneErg\OpenApi\Exceptions\InvalidArgumentOpenapiException;
use EugeneErg\OpenApi\Extensions;
use EugeneErg\OpenApi\Process;
use stdClass;

/**
 * Example Object.
 *
 * An object of its own rather than a bare value: first, the specification asks for an
 * object; second, deduplication in this package works by identity, and scalars have none
 * — two equal strings are indistinguishable, and a reference would end up in the wrong
 * place.
 */
final readonly class Example
{
    public Extensions $extensions;

    public function __construct(
        public ?AbstractValue $value = null,
        public ?string $summary = null,
        public ?string $description = null,
        public ?string $externalValue = null,
        ?Extensions $extensions = null,
    ) {
        $this->extensions = $extensions ?? new Extensions();

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

        return (object) $this->extensions->appendTo($result);
    }
}
