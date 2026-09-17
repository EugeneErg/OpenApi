<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi;

use EugeneErg\OpenApi\Components\Callbacks;
use EugeneErg\OpenApi\Components\Examples;
use EugeneErg\OpenApi\Components\Examples\Example;
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
use EugeneErg\OpenApi\Components\Schemas\Abstract\DeferredSchema;
use EugeneErg\OpenApi\Components\SecuritySchemes;
use EugeneErg\OpenApi\Components\SecuritySchemes\AbstractSecurityScheme;
use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows\Scope;
use EugeneErg\OpenApi\Exceptions\ComponentsNotFoundOpenapiException;
use EugeneErg\OpenApi\Exceptions\InvalidSchemaOpenapiException;
use EugeneErg\OpenApi\Exceptions\OperationNotFoundOpenapiException;
use EugeneErg\OpenApi\Paths\Path;
use stdClass;

use function sprintf;

/**
 * Контекст сборки одного документа.
 *
 * Каждый toObject() получает Process и через него спрашивает: «этот объект уже лежит
 * в каком-нибудь components?». Если да — на его месте окажется $ref, локальный или
 * с именем соседнего файла. Если нет — объект разворачивается по месту.
 */
final readonly class Process
{
    public function __construct(
        public Builder $builder,
        public Openapi $openapi,
    ) {
    }

    public function version(): Version
    {
        return $this->openapi->version;
    }

    /**
     * Возможности, появившиеся только в 3.1: словарь JSON Schema 2020-12
     * (схема 3.0 — это урезанный Draft 4) и собственные поля Reference Object.
     */
    public function assertV31(string $feature): void
    {
        if (!$this->version()->isV31()) {
            throw new InvalidSchemaOpenapiException(sprintf(
                '%s is only available in OpenAPI 3.1, got %s.',
                $feature,
                $this->version()->value,
            ));
        }
    }

    /**
     * Единая точка разрешения ссылки: по типу цели выбирается нужный поиск.
     * Используется Reference Object; отдельного «ручного» $ref в пакете нет.
     */
    public function refTo(
        AbstractSchema|AbstractSchemaParameter|ContentParameter|Example|Link|Path|PathItems|RequestBody|Response $target,
    ): ?stdClass {
        return match (true) {
            $target instanceof AbstractSchema => $this->findSchema($target),
            $target instanceof Response => $this->findResponse($target),
            $target instanceof RequestBody => $this->findRequestBody($target),
            $target instanceof Link => $this->findLink($target),
            $target instanceof Example => $this->findExample($target),
            $target instanceof Path => $this->findPathItem($target),
            $target instanceof PathItems => $this->findCallback($target),
            // ContentParameter не знает своего in: он берётся из места использования,
            // поэтому как компонент такой параметр адресуется только через headers
            $target instanceof ContentParameter => $this->findHeader($target),
            $target instanceof HeaderSchemaParameter => $this->findHeader($target) ?? $this->findParameter($target),
            default => $this->findParameter($target),
        };
    }

    public function findResponse(Response $value): ?stdClass
    {
        return $this->toRef(static fn (Openapi $openapi) => $openapi->findResponse($value), 'responses');
    }

    public function findRequestBody(RequestBody $value): ?stdClass
    {
        return $this->toRef(static fn (Openapi $openapi) => $openapi->findRequestBody($value), 'requestBodies');
    }

    public function findLink(Link $value): ?stdClass
    {
        return $this->toRef(static fn (Openapi $openapi) => $openapi->findLink($value), 'links');
    }

    public function findHeader(ContentParameter|HeaderSchemaParameter $value): ?stdClass
    {
        return $this->toRef(static fn (Openapi $openapi) => $openapi->findHeader($value), 'headers');
    }

    public function findSchema(AbstractSchema $value): ?stdClass
    {
        // отложенная ссылка из рекурсии указывает на ту же зарегистрированную схему
        if ($value instanceof DeferredSchema) {
            $value = $value->resolve();
        }

        return $this->toRef(static fn (Openapi $openapi) => $openapi->findSchema($value), 'schemas');
    }

    public function findPathItem(Path $value): ?stdClass
    {
        return $this->toRef(static fn (Openapi $openapi) => $openapi->findPathItem($value), 'pathItems');
    }

    public function findCallback(PathItems $value): ?stdClass
    {
        return $this->toRef(static fn (Openapi $openapi) => $openapi->findCallback($value), 'callbacks');
    }

    public function findExample(Example $value): ?stdClass
    {
        return $this->toRef(static fn (Openapi $openapi) => $openapi->findExample($value), 'examples');
    }

    public function findParameter(AbstractSchemaParameter|CustomParameter $value): ?stdClass
    {
        return $this->toRef(static fn (Openapi $openapi) => $openapi->findParameter($value), 'parameters');
    }

    public function findOperation(Paths\Operation $value): string
    {
        $result = $this->toPointer(static fn (Openapi $openapi) => $openapi->findOperation($value), 'paths');

        if ($result === null) {
            throw new OperationNotFoundOpenapiException(
                'Operation is not registered in paths of any document passed to the Builder.',
            );
        }

        return $result;
    }

    public function findSchemas(AbstractSchemas $value): ?stdClass
    {
        return $this->toComponentsRef(static fn (Openapi $openapi) => $openapi->components->schemas === $value, 'schemas');
    }

    public function findCallbacks(Callbacks $value): ?stdClass
    {
        return $this->toComponentsRef(static fn (Openapi $openapi) => $openapi->components->callbacks === $value, 'callbacks');
    }

    public function findParameters(Parameters $value): ?stdClass
    {
        return $this->toComponentsRef(static fn (Openapi $openapi) => $openapi->components->parameters === $value, 'parameters');
    }

    public function findLinks(Links $value): ?stdClass
    {
        return $this->toComponentsRef(static fn (Openapi $openapi) => $openapi->components->links === $value, 'links');
    }

    public function findSecuritySchemes(SecuritySchemes $value): ?stdClass
    {
        return $this->toComponentsRef(
            static fn (Openapi $openapi) => $openapi->components->securitySchemes === $value,
            'securitySchemes',
        );
    }

    public function findHeaders(Headers $value): ?stdClass
    {
        return $this->toComponentsRef(static fn (Openapi $openapi) => $openapi->components->headers === $value, 'headers');
    }

    public function findRequestBodies(RequestBodies $value): ?stdClass
    {
        return $this->toComponentsRef(
            static fn (Openapi $openapi) => $openapi->components->requestBodies === $value,
            'requestBodies',
        );
    }

    public function findExamples(Examples $value): ?stdClass
    {
        return $this->toComponentsRef(static fn (Openapi $openapi) => $openapi->components->examples === $value, 'examples');
    }

    public function findResponses(Responses $value): ?stdClass
    {
        return $this->toComponentsRef(static fn (Openapi $openapi) => $openapi->components->responses === $value, 'responses');
    }

    /**
     * Security Requirement Object ссылается на securitySchemes того же документа,
     * поэтому scope и схема ищутся без выхода в соседние файлы.
     */
    public function findScope(Scope $value): stdClass
    {
        return $this->openapi->findScope($value);
    }

    public function findSecurity(AbstractSecurityScheme $value): stdClass
    {
        return (object) [$this->openapi->findSecurity($value) => []];
    }

    /**
     * @param callable(Openapi): ?string $callback
     */
    private function toRef(callable $callback, string $component): ?stdClass
    {
        $result = $this->toPointer($callback, 'components/' . $component);

        return $result === null ? null : (object) ['$ref' => $result];
    }

    /**
     * Сначала текущий документ (локальная ссылка), затем остальные (ссылка с именем файла).
     *
     * @param callable(Openapi): ?string $callback
     */
    private function toPointer(callable $callback, string $component): ?string
    {
        $result = $callback($this->openapi);

        if ($result !== null) {
            return '#/' . $component . '/' . $result;
        }

        foreach ($this->builder->openapi as $fileName => $item) {
            if ($item !== $this->openapi) {
                $result = $callback($item);

                if ($result !== null) {
                    return $fileName . '#/' . $component . '/' . $result;
                }
            }
        }

        return null;
    }

    /**
     * Ссылка на секцию components целиком: она либо своя (тогда разворачивается по месту),
     * либо принадлежит документу, объявленному раньше текущего.
     *
     * @param callable(Openapi): bool $callback
     */
    private function toComponentsRef(callable $callback, string $component): ?stdClass
    {
        foreach ($this->builder->openapi as $fileName => $item) {
            if ($item === $this->openapi) {
                return null;
            }

            if ($callback($item)) {
                return (object) ['$ref' => $fileName . '#/components/' . $component];
            }
        }

        throw new ComponentsNotFoundOpenapiException(sprintf(
            'components.%s is not registered in any document passed to the Builder.',
            $component,
        ));
    }
}
