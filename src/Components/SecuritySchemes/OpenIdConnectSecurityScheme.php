<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\SecuritySchemes;

use stdClass;

final readonly class OpenIdConnectSecurityScheme extends AbstractSecurityScheme
{
    public function __construct(public string $openIdConnectUrl, ?string $description = null)
    {
        parent::__construct('openIdConnect', $description);
    }

    public function toObject(): stdClass
    {
        $result = parent::toObject();
        $result->openIdConnectUrl = $this->openIdConnectUrl;

        return $result;
    }
}
