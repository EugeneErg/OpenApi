<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Parameters\Abstract;

use EugeneErg\OpenApi\Components\Parameters\In;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValue;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValues;
use EugeneErg\OpenApi\Components\Schemas\Untyped\Values;
use EugeneErg\OpenApi\Process;
use stdClass;

abstract readonly class AbstractSchemaParameter extends AbstractParameter
{
    public AbstractValues|AbstractValue $examples;

    public function __construct(
        public In $in,
        public AbstractSchema $schema,
        public bool $explode = true,
        ?string $description = null,
        ?bool $required = false,
        ?bool $deprecated = false,
        null|AbstractValues|AbstractValue $examples = null,
    ) {
        parent::__construct($description, $required, $deprecated);
        $this->examples = $examples ?? new Values();
    }

    public function toObject(Process $process): stdClass
    {
        $result = parent::toObject($process);
        $result->in = $this->in->value;
        $result->schema = $this->schema->toObject($process);

        $defaultValues = $this->getDefaultValues();

        if (($defaultValues['explode'] ?? null) !== $this->explode) {
            $result->explode = $this->explode;
        }

        return $result;
    }

    protected function getDefaultValues(): array
    {
        return [];
    }
}
