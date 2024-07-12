<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows;

final readonly class ImplicitFlow extends AbstractFlow
{
    public function __construct(
        public string $authorizationUrl,
        Scopes $scopes,
        ?string $refreshUrl = null,
    ) {
        parent::__construct($scopes, $refreshUrl);
    }
}
