<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\String;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValue;

final readonly class Value extends AbstractValue
{
    public function __construct(?string $value)
    {
        parent::__construct($value);
    }
}
