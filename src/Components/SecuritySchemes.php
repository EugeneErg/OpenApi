<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components;

use EugeneErg\OpenApi\Components\SecuritySchemes\AbstractSecurityScheme;
use EugeneErg\OpenApi\Support\NamedItems;
use stdClass;

final readonly class SecuritySchemes
{
    use NamedItems;

    /** @var array<array-key, AbstractSecurityScheme> */
    public array $items;

    public function __construct(AbstractSecurityScheme ...$securitySchemes)
    {
        $this->items = self::named($securitySchemes);
    }

    public function sourceToObject(): stdClass
    {
        $result = [];

        foreach ($this->items as $name => $item) {
            $result[$name] = $item->toObject();
        }

        return (object) $result;
    }
}
