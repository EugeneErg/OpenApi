<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows;

use EugeneErg\OpenApi\Extensions;

final readonly class ClientCredentialsFlow extends AbstractFlow
{
    public function __construct(
        public string $tokenUrl,
        Scopes $scopes,
        ?string $refreshUrl = null,
        ?Extensions $extensions = null,
    ) {
        parent::__construct($scopes, $refreshUrl, $extensions);
    }

    protected function getUrls(): array
    {
        return ['tokenUrl' => $this->tokenUrl];
    }
}
