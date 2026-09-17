<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Securities;

use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Scheme as Oauth2Scheme;
use EugeneErg\OpenApi\Components\SecuritySchemes\OpenIdConnectSecurityScheme;
use EugeneErg\OpenApi\Exceptions\InvalidArgumentOpenapiException;
use EugeneErg\OpenApi\Process;
use stdClass;

/**
 * Скоуп, названный именем, а не объектом.
 *
 * Спецификация требует от Security Requirement только того, чтобы имя схемы было
 * объявлено в `components.securitySchemes`; сами скоупы объявлять не обязательно.
 * У openIdConnect их и негде объявить — список публикует провайдер по
 * openIdConnectUrl, — а у oauth2 документ может требовать скоуп, которого нет ни
 * в одном flow.
 *
 * Скоуп, объявленный во flow, передаётся объектом `Flows\Scope`: тогда его имя
 * и схема берутся из самого объявления и разойтись не могут.
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
