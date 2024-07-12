<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Parameters\Cookie;

use EugeneErg\OpenApi\Components\Parameters\Abstract\AbstractParameters;
use EugeneErg\OpenApi\Components\Parameters\ContentParameter;

/**
 * @property array<string, ContentParameter|SchemaParameter> $items
 */
final readonly class Cookies extends AbstractParameters
{
    public function __construct(ContentParameter|SchemaParameter ...$cookies)
    {
        parent::__construct(...$cookies);
    }
}
