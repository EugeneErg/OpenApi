<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\SecuritySchemes;

use stdClass;

final readonly class BearerHttpSecurityScheme extends AbstractSecurityScheme
{
    public string $scheme;

    public function __construct(public ?string $format = null, ?string $description = null)
    {
        $this->scheme = 'bearer';

        parent::__construct('http', $description);
    }

    public function toObject(): stdClass
    {
        $result = parent::toObject();
        $result->scheme = $this->scheme;

        if ($this->format !== null) {
            $result->bearerFormat = $this->format;
        }

        return $result;
    }
}
