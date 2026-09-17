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
 * Роль, которую требует операция у схемы без скоупов (OpenAPI 3.1).
 *
 * Спецификация 3.0 требует, чтобы у схем других типов список был пуст; роли
 * появились в 3.1. У oauth2 и openIdConnect в списке стоят скоупы — для них есть
 * `Flows\Scope` и `ScopeName`, поэтому схемы этих двух типов сюда не передать:
 * тип параметра их не допускает.
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
