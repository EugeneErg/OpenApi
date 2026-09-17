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
 * One Security Requirement: every scheme listed is required at once.
 *
 * A scheme with nothing added gives `{name: []}`, a Scope gives a scope of an oauth2
 * scheme, a ScopeName a scope named by its name, a Role a role of any other scheme (3.1).
 * An empty set gives `{}`: that is how the specification allows anonymous access.
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
