<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Parameters\Abstract;

use EugeneErg\OpenApi\Components\Examples;
use EugeneErg\OpenApi\Components\Parameters\In;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValue;
use EugeneErg\OpenApi\Exceptions\InvalidArgumentOpenapiException;
use EugeneErg\OpenApi\Process;
use stdClass;

abstract readonly class AbstractSchemaParameter extends AbstractParameter
{
    public Examples $examples;

    public function __construct(
        public In $in,
        public AbstractSchema $schema,
        public bool $explode = true,
        ?string $description = null,
        ?bool $required = false,
        ?bool $deprecated = false,
        public ?AbstractValue $example = null,
        ?Examples $examples = null,
    ) {
        parent::__construct($description, $required, $deprecated);

        if ($example !== null && $examples !== null && $examples->items !== []) {
            throw new InvalidArgumentOpenapiException(
                'Parameter cannot have both example and examples: they are mutually exclusive.',
            );
        }

        $this->examples = $examples ?? new Examples();
    }

    public function toObject(Process $process): stdClass
    {
        $result = parent::toObject($process);
        $result->in = $this->in->value;
        $result->schema = $process->findSchema($this->schema) ?? $this->schema->toObject($process);

        $defaultValues = $this->getDefaultValues();

        if (($defaultValues['explode'] ?? null) !== $this->explode) {
            $result->explode = $this->explode;
        }

        if ($this->example !== null) {
            $result->example = $this->example->toNative($process);
        }

        if ($this->examples->items !== []) {
            $result->examples = $this->examples->toObject($process);
        }

        return $result;
    }

    /**
     * @return array<string, bool>
     */
    protected function getDefaultValues(): array
    {
        return [];
    }
}
