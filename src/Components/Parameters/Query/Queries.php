<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Parameters\Query;

use EugeneErg\OpenApi\Components\Parameters\Abstract\AbstractParameters;
use EugeneErg\OpenApi\Components\Parameters\ContentParameter;
use EugeneErg\OpenApi\Components\Parameters\In;
use EugeneErg\OpenApi\Reference;

/**
 * @property array<array-key, ContentParameter|Reference|SchemaParameter> $items
 * @property list<ContentParameter|Reference|SchemaParameter> $registered
 */
final readonly class Queries extends AbstractParameters
{
    public function __construct(ContentParameter|Reference|SchemaParameter ...$queries)
    {
        parent::__construct(...$queries);
    }

    public function in(): In
    {
        return In::Query;
    }
}
