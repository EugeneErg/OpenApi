<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\RequestBodies;

enum EncodingStyle: string
{
    case Form = 'form';
    case SpaceDelimited = 'spaceDelimited';
    case PipeDelimited = 'pipeDelimited';
    case DeepObject = 'deepObject';
}
