<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows;

use EugeneErg\OpenApi\Support\NamedItems;
use stdClass;

final readonly class Scopes
{
    use NamedItems;

    /** @var array<array-key, Scope> */
    public array $items;

    public function __construct(Scope ...$scopes)
    {
        $this->items = self::named($scopes);
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
