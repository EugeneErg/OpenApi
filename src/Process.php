<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi;

use EugeneErg\OpenApi\Components\Callbacks;
use EugeneErg\OpenApi\Components\Headers;
use EugeneErg\OpenApi\Components\Links;
use EugeneErg\OpenApi\Components\Links\Link;
use EugeneErg\OpenApi\Components\Parameters;
use EugeneErg\OpenApi\Components\Parameters\Abstract\AbstractSchemaParameter;
use EugeneErg\OpenApi\Components\Parameters\ContentParameter;
use EugeneErg\OpenApi\Components\Parameters\CustomParameter;
use EugeneErg\OpenApi\Components\Parameters\Header\SchemaParameter as HeaderSchemaParameter;
use EugeneErg\OpenApi\Components\RequestBodies;
use EugeneErg\OpenApi\Components\RequestBodies\RequestBody;
use EugeneErg\OpenApi\Components\Responses;
use EugeneErg\OpenApi\Components\Responses\Response;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchemas;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValues;
use EugeneErg\OpenApi\Components\SecuritySchemes;
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

    public function findSchemas(AbstractSchemas $value): ?stdClass
    {
        return $this->builder->findSchemas($this->openapi, $value);
    }

    public function findCallbacks(Callbacks $value): ?stdClass
    {
        return $this->builder->findCallbacks($this->openapi, $value);
    }

    public function findParameters(Parameters $value): ?stdClass
    {
        return $this->builder->findParameters($this->openapi, $value);
    }

    public function findLinks(Links $value): ?stdClass
    {
        return $this->builder->findLinks($this->openapi, $value);
    }

    public function findSecuritySchemes(SecuritySchemes $value): ?stdClass
    {
        return $this->builder->findSecuritySchemes($this->openapi, $value);
    }

    public function findHeaders(Headers $value): ?stdClass
    {
        return $this->builder->findHeaders($this->openapi, $value);
    }

    public function findRequestBodies(RequestBodies $value): ?stdClass
    {
        return $this->builder->findRequestBodies($this->openapi, $value);
    }

    public function findExamples(AbstractValues $value): ?stdClass
    {
        return $this->builder->findExamples($this->openapi, $value);
    }

    public function findResponses(Responses $value): ?stdClass
    {
        return $this->builder->findResponses($this->openapi, $value);
    }
}
