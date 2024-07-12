<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Parameters\Query;

enum Style: string
{
    case Form = 'form';
    case SpaceDelimited = 'spaceDelimited';
    case PipeDelimited = 'pipeDelimited';
    case DeepObject = 'deepObject';
}
