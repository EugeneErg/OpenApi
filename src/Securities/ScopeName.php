<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Securities;

use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Scheme as Oauth2Scheme;
use EugeneErg\OpenApi\Components\SecuritySchemes\OpenIdConnectSecurityScheme;
use EugeneErg\OpenApi\Exceptions\InvalidArgumentOpenapiException;
use EugeneErg\OpenApi\Process;
use stdClass;

/**
 * A scope named by its name rather than by the object.
 *
 * Of a Security Requirement the specification asks only that the scheme's name be
 * declared in `components.securitySchemes`; the scopes themselves need not be declared.
 * With openIdConnect there is nowhere to declare them — the provider publishes the list at
 * openIdConnectUrl — and with oauth2 a document may require a scope that no flow has.
 *
 * A scope declared in a flow is passed as a `Flows\Scope` object: then its name and its
 * scheme come from the declaration itself and cannot drift apart.
 */
final readonly class ScopeName
{
    public function __construct(
        public Oauth2Scheme|OpenIdConnectSecurityScheme $scheme,
        public string $name,
    ) {
        if ($name === '') {
            throw new InvalidArgumentOpenapiException('Scope name must not be empty.');
        }
    }

    public function toTargetArray(Process $process): stdClass
    {
        return (object) [$process->openapi->findSecurity($this->scheme) => [$this->name]];
    }
}
