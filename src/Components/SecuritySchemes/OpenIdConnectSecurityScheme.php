<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\SecuritySchemes;

final readonly class OpenIdConnectSecurityScheme extends AbstractSecurityScheme
{
    public function __construct()
    {
        parent::__construct('openIdConnect');
    }
}
