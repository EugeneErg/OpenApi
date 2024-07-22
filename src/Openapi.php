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
use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Scheme;
use EugeneErg\OpenApi\Exceptions\ScopeNotFoundOpenapiException;
use EugeneErg\OpenApi\Exceptions\SecuritySchemeNotFoundOpenapiException;
use stdClass;

final readonly class Openapi
{
    public string $openapi;
    public Components $components;
    public Securities $security;
    public Tags $tags;
    public Paths $paths;

    public function __construct(
        public Info $info,
        Paths $paths = null,
        ?Components $components = null,
        ?Securities $security = null,
        ?Tags $tags = null,
        public ?ExternalDocs $externalDocs = null,
    ) {
        $this->openapi = '3.0.3';
        $this->paths = $paths ?? new Paths();
        $this->components = $components ?? new Components();
        $this->security = $security ?? new Securities();
        $this->tags = $tags ?? new Tags();
    }

    public function toObject(Process $process): stdClass
    {
        $result = [
            'openapi' => $this->openapi,
            'info' => $this->info->toObject(),
            'paths' => $this->paths->toObject($process),
        ];

        if ($this->externalDocs !== null) {
            $result['externalDocs'] = $this->externalDocs->toObject();
        }

        if ($this->tags->items !== []) {
            $result['tags'] = $this->tags->toArray();
        }

        if ($this->security->items !== []) {
            $result['security'] = $this->security->toArray($process);
        }

        if (!$this->components->isEmpty()) {
            $result['components'] = $this->components->toObject($process);
        }

        return (object) $result;
    }

    public function findResponse(Response $value): ?string
    {
        return $this->nullOrString(array_search($value, $this->components->responses->items, true));
    }

    public function findRequestBody(RequestBody $value): ?string
    {
        return $this->nullOrString(array_search($value, $this->components->requestBodies->items, true));
    }

    public function findLink(Link $value): ?string
    {
        return $this->nullOrString(array_search($value, $this->components->links->items, true));
    }

    public function findHeader(ContentParameter|HeaderSchemaParameter $value): ?string
    {
        return $this->nullOrString(array_search($value, $this->components->headers->items, true));
    }

    public function findSchema(AbstractSchema $value): ?string
    {
        return $this->nullOrString(array_search($value, $this->components->schemas->items, true));
    }

    public function findCallback(Paths $value): ?string
    {
        return $this->nullOrString(array_search($value, $this->components->callbacks->items, true));
    }

    public function findExample(null|AbstractValues|int|float|string|bool $value): ?string
    {
        return $this->nullOrString(array_search($value, $this->components->examples->items, true));
    }

    public function findOperation(Paths\Operation $operation): ?string
    {
        foreach ($this->paths->items as $pathName => $path) {
            $searchName = array_search($operation, $path->operations, true);

            if ($searchName !== false) {
                return $this->quotePath($pathName) . '/' . $this->quotePath($searchName);
            }
        }

        return null;
    }

    public function findParameter(CustomParameter|AbstractSchemaParameter $value): ?string
    {
        if ($value instanceof CustomParameter) {
            foreach ($this->components->parameters->items as $searchName => $parameter) {
                if (
                    $parameter->parameter instanceof CustomParameter
                    && $value->in === $parameter->parameter->in
                    && $value->contentParameter === $parameter->parameter->contentParameter
                ) {
                    return $this->quotePath($searchName);
                }
            }

            return null;
        }

        foreach ($this->components->parameters->items as $searchName => $parameter) {
            if ($parameter->parameter === $value) {
                return $this->quotePath($searchName);
            }
        }

        return null;
    }

    public function findScope(Scope $value): stdClass
    {
        //todo can contain in other openapi ??!!
        foreach ($this->components->securitySchemes->items as $schemeName => $scheme) {
            if ($scheme instanceof Scheme) {
                foreach ($scheme->flows->items as $scopes) {
                    $name = array_search($value, $scopes->scopes->items);

                    if ($name !== false) {
                        return (object) [$schemeName => [$name]];
                    }
                }
            }
        }

        throw new ScopeNotFoundOpenapiException();
    }

    public function finsSecurity(AbstractSecurityScheme $value): string
    {
        $name = array_search($value, $this->components->securitySchemes->items);

        if ($name === false) {
            throw new SecuritySchemeNotFoundOpenapiException();
        }

        return $name;
    }

    private function nullOrString(false|int|string $value): ?string
    {
        return $value === false ? null : $this->quotePath((string) $value);
    }

    private function quotePath(string $value): string
    {
        return str_replace(['~', '/'], ['~0', '~1'], $value);
    }
}
