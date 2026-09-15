<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows;

use stdClass;

final readonly class Scopes
{
    /** @var array<string, Scope> */
    public array $items;

    public function __construct(Scope ...$scopes)
    {
        /** @var array<string, Scope> $scopes */
        $this->items = $scopes;
    }

    /**
     * Карта «имя скоупа => описание».
     */
    public function toObject(): stdClass
    {
        $result = [];

        foreach ($this->items as $name => $scope) {
            $result[$name] = $scope->value;
        }

        return (object) $result;
    }
}
