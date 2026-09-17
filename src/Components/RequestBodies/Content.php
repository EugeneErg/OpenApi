<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\RequestBodies;

use EugeneErg\OpenApi\Components\Examples;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValue;
use EugeneErg\OpenApi\Exceptions\InvalidArgumentOpenapiException;
use EugeneErg\OpenApi\Extensions;
use EugeneErg\OpenApi\Process;
use stdClass;

final readonly class Content
{
    public Extensions $extensions;

    public Examples $examples;
    public Encodings $encoding;

    public function __construct(
        public ?AbstractSchema $schema = null,
        public ?AbstractValue $example = null,
        ?Examples $examples = null,
        ?Encodings $encoding = null,
        ?Extensions $extensions = null,
    ) {
        $this->extensions = $extensions ?? new Extensions();

        if ($example !== null && $examples !== null && $examples->items !== []) {
            throw new InvalidArgumentOpenapiException(
                'Media type cannot have both example and examples: they are mutually exclusive.',
            );
        }

        $this->examples = $examples ?? new Examples();
        $this->encoding = $encoding ?? new Encodings();
    }

    public function toObject(Process $process): stdClass
    {
        $result = [];

        // по спецификации media type может не описывать схему вовсе
        if ($this->schema !== null) {
            $result['schema'] = $process->findSchema($this->schema) ?? $this->schema->toObject($process);
        }

        if ($this->example !== null) {
            $result['example'] = $this->example->toNative($process);
        }

        if ($this->examples->items !== []) {
            $result['examples'] = $this->examples->toObject($process);
        }

        if ($this->encoding->items !== []) {
            $result['encoding'] = $this->encoding->toObject($process);
        }

        return (object) $this->extensions->appendTo($result);
    }
}
