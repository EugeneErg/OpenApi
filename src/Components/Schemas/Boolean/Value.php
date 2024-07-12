<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Boolean;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValue;

final readonly class Value extends AbstractValue
{
    public function __construct(int $value)
    {
        parent::__construct($value);
    }
}
