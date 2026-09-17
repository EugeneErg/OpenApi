<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Parameters\Cookie;

use EugeneErg\OpenApi\Components\Parameters\Abstract\AbstractParameters;
use EugeneErg\OpenApi\Components\Parameters\ContentParameter;
use EugeneErg\OpenApi\Components\Parameters\In;
use EugeneErg\OpenApi\Reference;

/**
 * @property array<array-key, ContentParameter|Reference|SchemaParameter> $items
 * @property list<ContentParameter|Reference|SchemaParameter> $registered
 */
final readonly class Cookies extends AbstractParameters
{
    public function __construct(ContentParameter|Reference|SchemaParameter ...$cookies)
    {
        parent::__construct(...$cookies);
    }

    public function in(): In
    {
        return In::Cookie;
    }
}
