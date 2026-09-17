<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Parameters\Path;

enum Style: string
{
    case Matrix = 'matrix';
    case Label = 'label';
    case Simple = 'simple';
}
