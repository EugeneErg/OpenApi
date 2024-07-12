<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\SecuritySchemes;

final readonly class BearerHttpSecurityScheme extends AbstractSecurityScheme
{
    public string $scheme;

    public function __construct()
    {
        $this->scheme = 'bearer';
        parent::__construct('http');
    }
}
