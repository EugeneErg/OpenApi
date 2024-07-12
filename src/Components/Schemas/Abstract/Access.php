<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Abstract;

enum Access: string
{
    case ReadOnly = 'readOnly';
    case WriteOnly = 'writeOnly';
}
