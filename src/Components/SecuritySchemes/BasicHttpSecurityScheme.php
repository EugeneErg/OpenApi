<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\SecuritySchemes;

use stdClass;

final readonly class BasicHttpSecurityScheme extends AbstractSecurityScheme
{
    public string $scheme;

    public function __construct(?string $description = null)
    {
        $this->scheme = 'basic';

        parent::__construct('http', $description);
    }

    public function toObject(): stdClass
    {
        $result = parent::toObject();
        $result->scheme = $this->scheme;

        return $result;
    }
}
