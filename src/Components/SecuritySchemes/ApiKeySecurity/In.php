<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\SecuritySchemes\ApiKeySecurity;

enum In: string
{
    case Query = 'query';
    case Header = 'header';
    case Cookie = 'cookie';
}
