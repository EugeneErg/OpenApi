<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Links;

use EugeneErg\OpenApi\Components\RequestBodies\Contents;

final readonly class RequestBody
{
    public function __construct(
        Contents $content,
    ) {
    }
}
