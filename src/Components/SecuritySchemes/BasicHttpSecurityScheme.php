<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\SecuritySchemes;

use EugeneErg\OpenApi\Extensions;
use stdClass;

final readonly class BasicHttpSecurityScheme extends AbstractSecurityScheme
{
    public string $scheme;

    public function __construct(
        ?string $description = null,
        ?Extensions $extensions = null,
    ) {
        $this->scheme = 'basic';

        parent::__construct(
            type: 'http',
            description: $description,
            extensions: $extensions,
        );
    }

    public function toObject(): stdClass
    {
        $result = parent::toObject();
        $result->scheme = $this->scheme;

        return (object) $this->extensions->appendTo($result);
    }
}
