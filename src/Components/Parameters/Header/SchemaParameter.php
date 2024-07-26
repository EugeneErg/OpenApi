<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Parameters\Header;

use EugeneErg\OpenApi\Components\Parameters\Abstract\AbstractSchemaParameter;
use EugeneErg\OpenApi\Components\Parameters\In;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValue;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValues;

final readonly class SchemaParameter extends AbstractSchemaParameter
{
    public function __construct(
        AbstractSchema $schema,
        bool $explode = true,
        null|AbstractValues|AbstractValue $examples = null,
        ?string $description = null,
        bool $required = false,
        bool $deprecated = false,
    ) {
        parent::__construct(In::Header, $schema, $explode, $description, $required, $deprecated, $examples);
    }

    protected function getDefaultValues(): array
    {
        return [
            'explode' => true,
        ];
    }
}
