<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security;

use EugeneErg\OpenApi\Components\SecuritySchemes\AbstractSecurityScheme;
use EugeneErg\OpenApi\Extensions;
use stdClass;

final readonly class Scheme extends AbstractSecurityScheme
{
    public function __construct(
        public Flows $flows,
        ?string $description = null,
        ?Extensions $extensions = null,
    ) {
        parent::__construct(
            type: 'oauth2',
            description: $description,
            extensions: $extensions,
        );
    }

    public function toObject(): stdClass
    {
        $result = parent::toObject();
        $result->flows = $this->flows->toObject();

        return (object) $this->extensions->appendTo($result);
    }
}
