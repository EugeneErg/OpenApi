<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi;

use EugeneErg\OpenApi\Components\Links\Link;
use EugeneErg\OpenApi\Components\Parameters\Abstract\AbstractSchemaParameter;
use EugeneErg\OpenApi\Components\Parameters\ContentParameter;
use EugeneErg\OpenApi\Components\Parameters\CustomParameter;
use EugeneErg\OpenApi\Components\Parameters\Header\SchemaParameter as HeaderSchemaParameter;
use EugeneErg\OpenApi\Components\RequestBodies\RequestBody;
use EugeneErg\OpenApi\Components\Responses\Response;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValues;
use EugeneErg\OpenApi\Components\SecuritySchemes\AbstractSecurityScheme;
use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows\Scope;
use stdClass;

final readonly class Process
{
    public function __construct(
        public Builder $builder,
        public Openapi $openapi
    ) {
    }

    public function findResponse(Response $value): ?stdClass
    {
        return $this->builder->findResponse($this->openapi, $value);
    }

    public function findRequestBody(RequestBody $value): ?stdClass
    {
        return $this->builder->findRequestBody($this->openapi, $value);
    }

    public function findLink(Link $value): ?stdClass
    {
        return $this->builder->findLink($this->openapi, $value);
    }

    public function findHeader(ContentParameter|HeaderSchemaParameter $value): ?stdClass
    {
        return $this->builder->findHeader($this->openapi, $value);
    }

    public function findSchema(AbstractSchema $value): ?stdClass
    {
        return $this->builder->findSchema($this->openapi, $value);
    }

    public function findCallback(Paths $value): ?stdClass
    {
        return $this->builder->findCallback($this->openapi, $value);
    }

    public function findExample(null|AbstractValues|int|float|string|bool $value): ?stdClass
    {
        return $this->builder->findExample($this->openapi, $value);
    }

    public function findOperation(Paths\Operation $value): string
    {
        return $this->builder->findOperation($this->openapi, $value);
    }

    public function findParameter(CustomParameter|AbstractSchemaParameter $value): ?stdClass
    {
        return $this->builder->findParameter($this->openapi, $value);
    }

    public function findScope(Scope $value): stdClass
    {
        return $this->openapi->findScope($value);
    }

    public function findSecurity(AbstractSecurityScheme $value): stdClass
    {
        return (object) [$this->openapi->finsSecurity($value) => []];
    }
}
