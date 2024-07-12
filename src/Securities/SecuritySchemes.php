<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Securities;

use EugeneErg\OpenApi\Components\SecuritySchemes\AbstractSecurityScheme;
use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows\Scope;
use EugeneErg\OpenApi\Process;
use stdClass;

/**
 * Каждая строка это ссылка на components.securitySchemes.{name}
 * значения, это ссылки на components.securitySchemes.{name}.flows.*.scopes.{scope}
 */
final readonly class SecuritySchemes
{
    /** @var array<Scope|AbstractSecurityScheme> */
    public array $items;

    public function __construct(Scope|AbstractSecurityScheme ...$scopes)
    {
        $this->items = $scopes;
    }

    public function toObject(Process $process): stdClass
    {
        $result = [];

        foreach ($this->items as $scopeOrScheme) {
            $result = array_merge_recursive($result, (array) $scopeOrScheme->toTargetArray($process));
        }

        return (object) $result;
    }
}
