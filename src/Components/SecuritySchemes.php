<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components;

use EugeneErg\OpenApi\Components\SecuritySchemes\AbstractSecurityScheme;
use stdClass;

final readonly class SecuritySchemes
{
    /** @var array<string, AbstractSecurityScheme> */
    public array $items;

    public function __construct(AbstractSecurityScheme ...$securitySchemes)
    {
        /** @var array<string, AbstractSecurityScheme> $securitySchemes */
        $this->items = $securitySchemes;
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
