<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows;

use EugeneErg\OpenApi\Process;
use stdClass;

final readonly class Scope
{
    public function __construct(public string $value)
    {
    }

    public function toTargetArray(Process $process): stdClass
    {
        return $process->findScope($this);
    }
}
