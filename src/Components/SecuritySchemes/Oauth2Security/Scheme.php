<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security;

use EugeneErg\OpenApi\Components\SecuritySchemes\AbstractSecurityScheme;
use stdClass;

final readonly class Scheme extends AbstractSecurityScheme
{
    public function __construct(public Flows $flows, ?string $description = null)
    {
        parent::__construct('oauth2', $description);
    }

    public function toObject(): stdClass
    {
        $result = parent::toObject();
        $result->flows = $this->flows->toObject();

        return $result;
    }
}
