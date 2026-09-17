<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Securities;

use EugeneErg\OpenApi\Components\SecuritySchemes\AbstractSecurityScheme;
use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows\Scope;
use EugeneErg\OpenApi\Process;
use EugeneErg\OpenApi\Serialization\Structure;
use EugeneErg\OpenApi\Support\ListedItems;
use stdClass;

/**
 * Одно Security Requirement: все перечисленные схемы требуются одновременно.
 *
 * Схема без уточнений даёт `{name: []}`, Scope — скоуп oauth2-схемы,
 * ScopeName — скоуп, названный именем, Role — роль любой другой схемы (3.1).
 * Пустой набор даёт `{}`: так спецификация разрешает анонимный доступ.
 */
final readonly class SecuritySchemes
{
    use ListedItems;

    /** @var array<AbstractSecurityScheme|Role|Scope|ScopeName> */
    public array $items;

    public function __construct(AbstractSecurityScheme|Role|Scope|ScopeName ...$scopes)
    {
        $this->items = self::listed($scopes);
    }

    public function toObject(Process $process): stdClass
    {
        /** @var array<string, list<string>> $result */
        $result = [];

        foreach ($this->items as $scopeOrScheme) {
            foreach (Structure::vars($scopeOrScheme->toTargetArray($process)) as $name => $names) {
                /** @var list<string> $names */
                $result[(string) $name] = array_values(array_unique([...$result[(string) $name] ?? [], ...$names]));
            }
        }

        return (object) $result;
    }
}
