<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Parameters\Path;

use EugeneErg\OpenApi\Components\Parameters\Abstract\AbstractParameters;
use EugeneErg\OpenApi\Components\Parameters\ContentParameter;
use EugeneErg\OpenApi\Components\Parameters\In;

/**
 * @property array<string, ContentParameter|SchemaParameter> $items
 */
final readonly class Paths extends AbstractParameters
{
    public function __construct(ContentParameter|SchemaParameter ...$paths)
    {
        parent::__construct(...$paths);
    }

    public function in(): In
    {
        return In::Path;
    }
}
