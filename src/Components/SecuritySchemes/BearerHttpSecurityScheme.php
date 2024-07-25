<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\SecuritySchemes;

use stdClass;

final readonly class BearerHttpSecurityScheme extends AbstractSecurityScheme
{
    public function __construct(public ?string $format = null)
    {
        parent::__construct('http');
    }

    public function toObject(): stdClass
    {
        $result = parent::toObject();

        if ($this->format !== null) {
            $result->bearerFormat = $this->format;
        }

        $result->scheme = 'bearer';

        return $result;
    }
}
