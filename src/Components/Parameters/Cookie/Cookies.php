<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Parameters\Cookie;

use EugeneErg\OpenApi\Components\Parameters\Abstract\AbstractParameters;
use EugeneErg\OpenApi\Components\Parameters\ContentParameter;
use EugeneErg\OpenApi\Components\Parameters\In;

/**
 * @property array<string, ContentParameter|SchemaParameter> $items
 */
final readonly class Cookies extends AbstractParameters
{
    public function __construct(ContentParameter|SchemaParameter ...$cookies)
    {
        parent::__construct(...$cookies);
    }

    public function in(): In
    {
        return In::Cookie;
    }
}
