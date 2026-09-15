<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Parameters\Path;

use EugeneErg\OpenApi\Components;
use EugeneErg\OpenApi\Components\Examples;
use EugeneErg\OpenApi\Components\Parameters\Abstract\AbstractSchemaParameter;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValue;
use EugeneErg\OpenApi\Process;
use stdClass;

final readonly class SchemaParameter extends AbstractSchemaParameter
{
    public function __construct(
        AbstractSchema $schema,
        bool $explode = false,
        ?AbstractValue $example = null,
        ?Examples $examples = null,
        ?string $description = null,
        bool $deprecated = false,
        public Style $style = Style::Simple,
    ) {
        parent::__construct(Components\Parameters\In::Path, $schema, $explode, $description, true, $deprecated, $example, $examples);
    }

    public function toObject(Process $process): stdClass
    {
        $result = parent::toObject($process);

        if ($this->style !== Style::Simple) {
            $result->style = $this->style->value;
        }

        return $result;
    }

    /**
     * @return array<string, bool>
     */
    protected function getDefaultValues(): array
    {
        return [
            'explode' => false,
        ];
    }
}
