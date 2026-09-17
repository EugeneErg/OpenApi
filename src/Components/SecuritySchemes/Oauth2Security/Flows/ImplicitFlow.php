<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows;

use EugeneErg\OpenApi\Extensions;

final readonly class ImplicitFlow extends AbstractFlow
{
    public function __construct(
        public string $authorizationUrl,
        Scopes $scopes,
        ?string $refreshUrl = null,
        ?Extensions $extensions = null,
    ) {
        parent::__construct(
            scopes: $scopes,
            refreshUrl: $refreshUrl,
            extensions: $extensions,
        );
    }

    protected function getUrls(): array
    {
        return ['authorizationUrl' => $this->authorizationUrl];
    }
}
