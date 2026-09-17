<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\SecuritySchemes;

use EugeneErg\OpenApi\Extensions;
use stdClass;

final readonly class BearerHttpSecurityScheme extends AbstractSecurityScheme
{
    public string $scheme;

    public function __construct(
        public ?string $format = null,
        ?string $description = null,
        ?Extensions $extensions = null,
    ) {
        $this->scheme = 'bearer';

        parent::__construct('http', $description, $extensions);
    }

    public function toObject(): stdClass
    {
        $result = parent::toObject();
        $result->scheme = $this->scheme;

        if ($this->format !== null) {
            $result->bearerFormat = $this->format;
        }

        return (object) $this->extensions->appendTo($result);
    }
}
