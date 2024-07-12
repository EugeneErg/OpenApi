<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Parameters\Path;

enum Style: string
{
    case matrix = 'matrix';
    case Label = 'label';
    case Simple = 'simple';
}
