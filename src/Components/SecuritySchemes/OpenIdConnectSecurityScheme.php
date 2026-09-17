<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\SecuritySchemes;

use EugeneErg\OpenApi\Extensions;
use stdClass;

final readonly class OpenIdConnectSecurityScheme extends AbstractSecurityScheme
{
    public function __construct(
        public string $openIdConnectUrl,
        ?string $description = null,
        ?Extensions $extensions = null,
    ) {
        parent::__construct(
            type: 'openIdConnect',
            description: $description,
            extensions: $extensions,
        );
    }

    public function toObject(): stdClass
    {
        $result = parent::toObject();
        $result->openIdConnectUrl = $this->openIdConnectUrl;

        return (object) $this->extensions->appendTo($result);
    }
}
