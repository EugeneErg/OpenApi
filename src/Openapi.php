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
use EugeneErg\OpenApi\Exceptions\Place;
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

        // in 3.1 paths are optional when webhooks or components are there;
        // an empty object adds nothing in that case
        if (
            $this->paths->items !== []
            || !$this->version->isV31()
            || $process->verbose
            || ($this->webhooks->items === [] && $this->components->isEmpty())
        ) {
            $result['paths'] = Place::in(fn (): stdClass => $this->paths->toObject($process), 'paths');
        }

        if ($this->jsonSchemaDialect !== null) {
            $result['jsonSchemaDialect'] = $this->jsonSchemaDialect;
        }

        if ($this->webhooks->items !== []) {
            $result['webhooks'] = Place::in(fn (): stdClass => $this->webhooks->toObject($process), 'webhooks');
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
     * The name a parameter is declared under in components. It is not written beside a
     * `$ref`, but the check for repeats in a parameter list compares exactly the names.
     */
    public function findParameterName(AbstractSchemaParameter|CustomParameter $value): ?string
    {
        return $this->parameterComponent($value)[1]->name ?? null;
    }

    /**
     * A Security Requirement Object refers to the securitySchemes of its own document, so
     * a scope is looked up in the current Openapi alone, without reaching the neighbours.
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
     * @return null|array{string, Parameter} the name in components and the declaration itself
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
     * The addresses of every schema of the document, relative to `components/schemas`.
     *
     * A schema inside $defs is addressed by a path such as `User/$defs/Address`, so a
     * reference to it is built by the same machinery as one to an ordinary component: the
     * user passes the object, and the path is worked out here. There is no second, manual
     * way to refer to a schema in this package.
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
                // the schema has been seen already: there is no need to recurse around
                // the cycle, and the first address found stays the canonical one
                continue;
            }

            $index[$id] = $prefix . self::quotePath((string) $name);

            if ($schema->resource !== null && $schema->resource->defs !== null) {
                self::collectSchemas($schema->resource->defs, $index[$id] . '/$defs/', $index);
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
     * Every {var} in a template must have a path parameter, and the other way round.
     *
     * Checked here rather than in Paths: a parameter's name comes from the key of the
     * local container, but when the parameter is registered in components.parameters it
     * comes from the name field of that registration.
     */
    private function assertPathParameters(): void
    {
        $names = [];

        foreach ($this->components->parameters->items as $registered) {
            $names[spl_object_id($registered->parameter)] = $registered->name;
        }

        foreach ($this->paths->items as $template => $path) {
            $template = (string) $template;

            // a reference to a Path Item is checked where the Path Item itself is declared
            if ($path instanceof Reference) {
                $path = $path->target;

                if (!$path instanceof Path) {
                    continue;
                }
            }

            $expected = Paths::templateVariables($template);
            $shared = self::namesOf($path->parameters, $names);

            // By the specification an empty Path Item (a hidden ACL, say) need not declare
            // the template's parameters, but the ones declared must still occur in it.
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
