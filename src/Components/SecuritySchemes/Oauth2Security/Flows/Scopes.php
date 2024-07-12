<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows;

final readonly class Scopes
{
    /** @var array<string, Scope> */
    public array $items;

    public function __construct(Scope ...$scopes)
    {
        /** @var array<string, Scope>$scopes */
        $this->items = $scopes;
    }
}
