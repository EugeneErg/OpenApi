<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\SecuritySchemes\ApiKeySecurity;

use EugeneErg\OpenApi\Components\SecuritySchemes\AbstractSecurityScheme;
use stdClass;

final readonly class Scheme extends AbstractSecurityScheme
{
    public function __construct(public string $name, public In $in, ?string $description = null)
    {
        parent::__construct('apiKey', $description);
    }

    public function toObject(): stdClass
    {
        $result = parent::toObject();
        $result->name = $this->name;
        $result->in = $this->in->value;

        return $result;
    }
}
