<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\RequestBodies;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValue;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValues;
use EugeneErg\OpenApi\Components\Schemas\Object\OpenapiObject;
use EugeneErg\OpenApi\Components\Schemas\Untyped\Values;
use EugeneErg\OpenApi\Process;
use stdClass;

final readonly class Content
{
    public AbstractValues|AbstractValue $examples;
    public Encodings $encoding;

    public function __construct(
        public AbstractSchema $schema,
        null|OpenapiObject|AbstractValue $examples = null,
        Encodings $encoding = null,
    ) {
        $this->examples = $examples ?? new Values();
        $this->encoding = $encoding ?? new Encodings();
    }

    public function toObject(Process $process): stdClass
    {
        $result = ['schema' => $process->findSchema($this->schema) ?? $this->schema->toObject($process)];

        if ($this->examples instanceof AbstractValue) {
            $result['example'] = $this->examples->toNative($process);
        } elseif ($this->examples->items !== []) {
            $result['examples'] = $this->examples->toNative($process);
        }

        if ($this->encoding->items !== []) {
            $result['encoding'] = $this->encoding->toObject($process);
        }

        return (object) $result;
    }
}
