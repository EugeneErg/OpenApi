<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Array;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValue;

final readonly class Value extends AbstractValue
{
    public function __construct(?OpenapiArray $value)
    {
        parent::__construct($value);
    }
}
