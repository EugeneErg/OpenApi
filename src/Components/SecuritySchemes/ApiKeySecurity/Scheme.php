<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\SecuritySchemes\ApiKeySecurity;

use EugeneErg\OpenApi\Components\SecuritySchemes\AbstractSecurityScheme;
use EugeneErg\OpenApi\Extensions;
use stdClass;

final readonly class Scheme extends AbstractSecurityScheme
{
    public function __construct(
        public string $name,
        public In $in,
        ?string $description = null,
        ?Extensions $extensions = null,
    ) {
        parent::__construct('apiKey', $description, $extensions);
    }

    public function toObject(): stdClass
    {
        $result = parent::toObject();
        $result->name = $this->name;
        $result->in = $this->in->value;

        return (object) $this->extensions->appendTo($result);
    }
}
