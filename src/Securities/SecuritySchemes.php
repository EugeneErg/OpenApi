<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Securities;

use EugeneErg\OpenApi\Components\SecuritySchemes\AbstractSecurityScheme;
use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows\Scope;
use EugeneErg\OpenApi\Process;
use EugeneErg\OpenApi\Serialization\Structure;
use stdClass;

/**
 * Каждая строка это ссылка на components.securitySchemes.{name}
 * значения, это ссылки на components.securitySchemes.{name}.flows.*.scopes.{scope}.
 */
final readonly class SecuritySchemes
{
    /** @var array<AbstractSecurityScheme|Scope> */
    public array $items;

    public function __construct(AbstractSecurityScheme|Scope ...$scopes)
    {
        $this->items = $scopes;
    }

    public function toObject(Process $process): stdClass
    {
        $result = [];

        foreach ($this->items as $scopeOrScheme) {
            $result = array_merge_recursive($result, Structure::vars($scopeOrScheme->toTargetArray($process)));
        }

        return (object) $result;
    }
}
