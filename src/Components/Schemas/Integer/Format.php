<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Integer;

enum Format: string
{
    case Int32 = 'int32';
    case Int64 = 'int64';
}
