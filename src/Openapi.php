<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi;

use EugeneErg\OpenApi\Components\Examples\Example;
use EugeneErg\OpenApi\Components\Links\Link;
use EugeneErg\OpenApi\Components\Parameters\Abstract\AbstractSchemaParameter;
use EugeneErg\OpenApi\Components\Parameters\ContentParameter;
use EugeneErg\OpenApi\Components\Parameters\CustomParameter;
use EugeneErg\OpenApi\Components\Parameters\Header\SchemaParameter as HeaderSchemaParameter;
use EugeneErg\OpenApi\Components\Parameters\Parameter;
use EugeneErg\OpenApi\Components\Parameters\Parameters as OperationParameters;
use EugeneErg\OpenApi\Components\RequestBodies\RequestBody;
use EugeneErg\OpenApi\Components\Responses\Response;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchemas;
use EugeneErg\OpenApi\Components\SecuritySchemes\AbstractSecurityScheme;
use EugeneErg\OpenApi\Components\SecuritySchemes\MutualTlsSecurityScheme;
use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows\Scope;
use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Scheme;
use EugeneErg\OpenApi\Exceptions\InvalidArgumentOpenapiException;
use EugeneErg\OpenApi\Exceptions\InvalidPathOpenapiException;
use EugeneErg\OpenApi\Exceptions\ScopeNotFoundOpenapiException;
use EugeneErg\OpenApi\Exceptions\SecuritySchemeNotFoundOpenapiException;
use EugeneErg\OpenApi\Paths\Path;
use stdClass;

use function count;
use function sprintf;

final readonly class Openapi
{
    public Extensions $extensions;

    public Components $components;
    public Securities $security;
    public Tags $tags;
    public Paths $paths;
    public PathItems $webhooks;
    public Servers $servers;

    /** @var array<int, string> */
    private array $schemaIndex;

    public function __construct(
        public Info $info,
        ?Components $components = null,
        ?Paths $paths = null,
        ?Servers $servers = null,
        ?Securities $security = null,
        ?Tags $tags = null,
        public ?ExternalDocs $externalDocs = null,
        public Version $version = Version::V303,
        public ?string $jsonSchemaDialect = null,
        ?PathItems $webhooks = null,
        ?Extensions $extensions = null,
    ) {
        $this->extensions = $extensions ?? new Extensions();

        if (!$version->isV31()) {
            self::assertNoV31Fields($version, [
                'webhooks' => $webhooks !== null && $webhooks->items !== [],
                'jsonSchemaDialect' => $jsonSchemaDialect !== null,
                'info.summary' => $info->summary !== null,
                'info.license.identifier' => $info->license?->identifier !== null,
                'components.pathItems' => ($components?->pathItems->items ?? []) !== [],
                'securitySchemes of type mutualTLS' => self::hasMutualTls($components),
            ]);
        }

        $this->webhooks = $webhooks ?? new PathItems();

        if ($this->webhooks->extensions->items !== []) {
            throw new InvalidArgumentOpenapiException(
                'webhooks is a plain map: its extensions belong to the OpenAPI object itself.',
            );
        }
        $this->paths = $paths ?? new Paths();
        $this->components = $components ?? new Components();
        $this->security = $security ?? new Securities();
        $this->tags = $tags ?? new Tags();
        $this->servers = $servers ?? new Servers();

        $this->schemaIndex = self::indexSchemas($this->components->schemas);

        $this->assertPathParameters();
    }

    public function toObject(Process $process): stdClass
    {
        $result = [
            'openapi' => $this->version->value,
            'info' => $this->info->toObject(),
        ];

        // в 3.1 paths необязательны, если есть webhooks или components;
        // пустой объект в этом случае ничего не добавляет
        if (
            $this->paths->items !== []
            || !$this->version->isV31()
            || ($this->webhooks->items === [] && $this->components->isEmpty())
        ) {
            $result['paths'] = $this->paths->toObject($process);
        }

        if ($this->jsonSchemaDialect !== null) {
            $result['jsonSchemaDialect'] = $this->jsonSchemaDialect;
        }

        if ($this->webhooks->items !== []) {
            $result['webhooks'] = $this->webhooks->toObject($process);
        }

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

        if ($this->servers->items !== []) {
            $result['servers'] = $this->servers->toArray();
        }

        return (object) $this->extensions->appendTo($result);
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
        return $this->schemaIndex[spl_object_id($value)] ?? null;
    }

    public function findPathItem(Path $value): ?string
    {
        $name = array_search($value, $this->components->pathItems->items, true);

        return $name === false ? null : self::quotePath((string) $name);
    }

    public function findCallback(PathItems $value): ?string
    {
        return $this->nullOrString(array_search($value, $this->components->callbacks->items, true));
    }

    public function findExample(Example $value): ?string
    {
        return $this->nullOrString(array_search($value, $this->components->examples->items, true));
    }

    public function findOperation(Paths\Operation $operation): ?string
    {
        foreach ($this->paths->items as $pathName => $path) {
            if ($path instanceof Reference) {
                continue;
            }

            $searchName = array_search($operation, $path->operations, true);

            if ($searchName !== false) {
                return self::quotePath((string) $pathName) . '/' . self::quotePath($searchName);
            }
        }

        return null;
    }

    public function findParameter(AbstractSchemaParameter|CustomParameter $value): ?string
    {
        $found = $this->parameterComponent($value);

        return $found === null ? null : self::quotePath($found[0]);
    }

    /**
     * Имя, под которым параметр объявлен в components. Рядом с `$ref` его не пишут,
     * но проверка на повторы в списке параметров сравнивает именно имена.
     */
    public function findParameterName(AbstractSchemaParameter|CustomParameter $value): ?string
    {
        return $this->parameterComponent($value)[1]->name ?? null;
    }

    /**
     * Security Requirement Object ссылается на securitySchemes того же документа,
     * поэтому scope ищется только в текущем Openapi, без выхода в соседние файлы.
     */
    public function findScope(Scope $value): stdClass
    {
        foreach ($this->components->securitySchemes->items as $schemeName => $scheme) {
            if ($scheme instanceof Scheme) {
                foreach ($scheme->flows->items as $flow) {
                    $name = array_search($value, $flow->scopes->items, true);

                    if ($name !== false) {
                        return (object) [$schemeName => [(string) $name]];
                    }
                }
            }
        }

        throw new ScopeNotFoundOpenapiException(sprintf(
            'Scope "%s" is not declared in any oauth2 flow of this document.',
            $value->value,
        ));
    }

    public function findSecurity(AbstractSecurityScheme $value): string
    {
        $name = array_search($value, $this->components->securitySchemes->items, true);

        if ($name === false) {
            throw new SecuritySchemeNotFoundOpenapiException(sprintf(
                'Security scheme of type "%s" is not registered in components.securitySchemes of this document.',
                $value->type,
            ));
        }

        return (string) $name;
    }

    /**
     * @return null|array{string, Parameter} имя в components и само объявление
     */
    private function parameterComponent(AbstractSchemaParameter|CustomParameter $value): ?array
    {
        foreach ($this->components->parameters->items as $searchName => $parameter) {
            $declared = $parameter->parameter;
            $same = $value instanceof CustomParameter
                ? $declared instanceof CustomParameter
                    && $value->in === $declared->in
                    && $value->contentParameter === $declared->contentParameter
                : $declared === $value;

            if ($same) {
                return [(string) $searchName, $parameter];
            }
        }

        return null;
    }

    /**
     * Адреса всех схем документа относительно `components/schemas`.
     *
     * Схема из $defs адресуется путём вида `User/$defs/Address`, поэтому ссылку
     * на неё строит тот же механизм, что и на обычный компонент: пользователь
     * передаёт объект, а путь считается здесь. Второго, ручного способа
     * сослаться на схему в пакете нет.
     *
     * @return array<int, string>
     */
    private static function indexSchemas(AbstractSchemas $schemas): array
    {
        $index = [];

        self::collectSchemas($schemas, '', $index);

        return $index;
    }

    /**
     * @param array<int, string> $index
     */
    private static function collectSchemas(AbstractSchemas $schemas, string $prefix, array &$index): void
    {
        foreach ($schemas->items as $name => $schema) {
            $id = spl_object_id($schema);

            if (isset($index[$id])) {
                // схема уже встречалась: рекурсия по циклу не нужна,
                // а первый найденный адрес остаётся каноническим
                continue;
            }

            $index[$id] = $prefix . self::quotePath((string) $name);

            if ($schema->defs !== null) {
                self::collectSchemas($schema->defs, $index[$id] . '/$defs/', $index);
            }
        }
    }

    /**
     * @param array<string, bool> $used
     */
    private static function assertNoV31Fields(Version $version, array $used): void
    {
        $found = array_keys(array_filter($used));

        if ($found !== []) {
            throw new InvalidArgumentOpenapiException(sprintf(
                '%s %s introduced in OpenAPI 3.1 and cannot be used with %s.',
                implode(', ', $found),
                count($found) === 1 ? 'was' : 'were',
                $version->value,
            ));
        }
    }

    private static function hasMutualTls(?Components $components): bool
    {
        foreach ($components?->securitySchemes->items ?? [] as $scheme) {
            if ($scheme instanceof MutualTlsSecurityScheme) {
                return true;
            }
        }

        return false;
    }

    /**
     * Каждая подстановка {var} должна иметь path-параметр, и наоборот.
     *
     * Проверяется здесь, а не в Paths: имя параметра берётся из ключа локального
     * контейнера, но если параметр зарегистрирован в components.parameters —
     * из поля name этой регистрации.
     */
    private function assertPathParameters(): void
    {
        $names = [];

        foreach ($this->components->parameters->items as $registered) {
            $names[spl_object_id($registered->parameter)] = $registered->name;
        }

        foreach ($this->paths->items as $template => $path) {
            $template = (string) $template;

            // ссылка на Path Item проверяется там, где объявлен сам Path Item
            if ($path instanceof Reference) {
                $path = $path->target;

                if (!$path instanceof Path) {
                    continue;
                }
            }

            $expected = Paths::templateVariables($template);
            $shared = self::namesOf($path->parameters, $names);

            // Пустой Path Item (например, скрытый ACL) по спецификации может не объявлять
            // параметры шаблона, но объявленные всё равно обязаны в нём встречаться.
            if ($path->operations === []) {
                self::assertMatches($template, null, $expected, $shared, requireAll: false);

                continue;
            }

            foreach ($path->operations as $method => $operation) {
                self::assertMatches($template, $method, $expected, array_values(array_unique([
                    ...$shared,
                    ...self::namesOf($operation->parameters, $names),
                ])));
            }
        }
    }

    /**
     * @param array<int, string> $names
     *
     * @return list<string>
     */
    private static function namesOf(OperationParameters $parameters, array $names): array
    {
        $result = [];

        foreach ($parameters->paths->items as $key => $parameter) {
            $result[] = $names[spl_object_id($parameter)] ?? (string) $key;
        }

        foreach ($parameters->paths->registered as $parameter) {
            $result[] = $names[spl_object_id($parameter)] ?? throw new InvalidPathOpenapiException(
                'A path parameter was passed without a name, but it is not registered in components.parameters: '
                . 'pass it by name, or register it so the name comes from the registration.',
            );
        }

        return $result;
    }

    /**
     * @param list<string> $expected
     * @param list<string> $declared
     */
    private static function assertMatches(
        string $template,
        ?string $method,
        array $expected,
        array $declared,
        bool $requireAll = true,
    ): void {
        $where = $method === null
            ? sprintf('Path %s', $template)
            : sprintf('Operation %s %s', strtoupper($method), $template);

        $missing = $requireAll ? array_diff($expected, $declared) : [];

        if ($missing !== []) {
            throw new InvalidPathOpenapiException(sprintf(
                '%s has no path parameter for {%s}.',
                $where,
                implode('}, {', $missing),
            ));
        }

        $extra = array_diff($declared, $expected);

        if ($extra !== []) {
            throw new InvalidPathOpenapiException(sprintf(
                '%s declares path parameter "%s", which does not appear in the template.',
                $where,
                implode('", "', $extra),
            ));
        }
    }

    private function nullOrString(false|int|string $value): ?string
    {
        return $value === false ? null : self::quotePath((string) $value);
    }

    private static function quotePath(string $value): string
    {
        return str_replace(['~', '/'], ['~0', '~1'], $value);
    }
}
