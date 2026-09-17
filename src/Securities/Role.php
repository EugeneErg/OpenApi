<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Securities;

use EugeneErg\OpenApi\Components\SecuritySchemes\ApiKeySecurity\Scheme as ApiKeyScheme;
use EugeneErg\OpenApi\Components\SecuritySchemes\BasicHttpSecurityScheme;
use EugeneErg\OpenApi\Components\SecuritySchemes\BearerHttpSecurityScheme;
use EugeneErg\OpenApi\Components\SecuritySchemes\HttpSecurityScheme;
use EugeneErg\OpenApi\Components\SecuritySchemes\MutualTlsSecurityScheme;
use EugeneErg\OpenApi\Exceptions\InvalidArgumentOpenapiException;
use EugeneErg\OpenApi\Process;
use stdClass;

/**
 * A role an operation requires of a scheme that has no scopes (OpenAPI 3.1).
 *
 * The 3.0 specification requires the list to be empty for schemes of other types; roles
 * appeared in 3.1. For oauth2 and openIdConnect the list holds scopes — `Flows\Scope` and
 * `ScopeName` are there for those — so schemes of those two types cannot be passed here:
 * the parameter's type does not admit them.
 */
final readonly class Role
{
    public function __construct(
        public ApiKeyScheme|BasicHttpSecurityScheme|BearerHttpSecurityScheme|HttpSecurityScheme|MutualTlsSecurityScheme $scheme,
        public string $name,
    ) {
        if ($name === '') {
            throw new InvalidArgumentOpenapiException('Role name must not be empty.');
        }
    }

    public function toTargetArray(Process $process): stdClass
    {
        $process->assertV31('Role names in a Security Requirement');

        return (object) [$process->openapi->findSecurity($this->scheme) => [$this->name]];
    }
}
