<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Number;

enum Format: string
{
    case Float = 'float';
    case Double = 'double';
}
