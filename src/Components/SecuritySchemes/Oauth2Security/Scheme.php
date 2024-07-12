<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security;

use EugeneErg\OpenApi\Components\SecuritySchemes\AbstractSecurityScheme;

final readonly class Scheme extends AbstractSecurityScheme
{
    public function __construct(public Flows $flows)
    {
        parent::__construct('oath2');
    }
}
