<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Parameters\Cookie;

use EugeneErg\OpenApi\Components\Examples;
use EugeneErg\OpenApi\Components\Parameters\Abstract\AbstractSchemaParameter;
use EugeneErg\OpenApi\Components\Parameters\In;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValue;
use EugeneErg\OpenApi\Extensions;

final readonly class SchemaParameter extends AbstractSchemaParameter
{
    public function __construct(
        AbstractSchema $schema,
        ?bool $explode = null,
        ?AbstractValue $example = null,
        ?Examples $examples = null,
        ?string $description = null,
        bool $required = false,
        bool $deprecated = false,
        ?Extensions $extensions = null,
    ) {
        parent::__construct(
            in: In::Cookie,
            schema: $schema,
            explode: $explode ?? true,
            description: $description,
            required: $required,
            deprecated: $deprecated,
            example: $example,
            examples: $examples,
            extensions: $extensions,
        );
    }

    /**
     * A cookie's style is form, and with it the specification sets explode = true.
     *
     * @return array<string, bool>
     */
    protected function getDefaultValues(): array
    {
        return [
            'explode' => true,
        ];
    }
}
