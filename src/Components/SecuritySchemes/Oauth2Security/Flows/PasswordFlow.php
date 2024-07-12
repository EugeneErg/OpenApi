<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows;

final readonly class PasswordFlow extends AbstractFlow
{
    public function __construct(
        public string $tokenUrl,
        Scopes $scopes,
        ?string $refreshUrl = null,
    ) {
        parent::__construct($scopes, $refreshUrl);
    }
}
