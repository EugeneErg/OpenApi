<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\String;

enum Format: string
{
    case Date = 'date';
    case DateTime = 'date-time';
    case Password = 'password';
    case Byte = 'byte';
    case Binary = 'binary';
    case Email = 'email';
    case Uuid = 'uuid';
    case Hostname = 'hostname';
    case IPv4 = 'ipv4';
    case IPv6 = 'ipv6';
    case Uri = 'uri';
}
