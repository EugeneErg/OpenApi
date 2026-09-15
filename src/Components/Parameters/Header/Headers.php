<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Parameters\Header;

use EugeneErg\OpenApi\Components\Parameters\Abstract\AbstractParameters;
use EugeneErg\OpenApi\Components\Parameters\ContentParameter;
use EugeneErg\OpenApi\Components\Parameters\In;

/**
 * @property array<string, ContentParameter|SchemaParameter> $items
 */
final readonly class Headers extends AbstractParameters
{
    public function __construct(ContentParameter|SchemaParameter ...$headers)
    {
        parent::__construct(...$headers);
    }

    public function in(): In
    {
        return In::Header;
    }
}
