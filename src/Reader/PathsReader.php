<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Reader;

use EugeneErg\OpenApi\Components\Schemas\String\Strings;
use EugeneErg\OpenApi\Components\SecuritySchemes\AbstractSecurityScheme;
use EugeneErg\OpenApi\Components\SecuritySchemes\ApiKeySecurity\Scheme as ApiKeyScheme;
use EugeneErg\OpenApi\Components\SecuritySchemes\BasicHttpSecurityScheme;
use EugeneErg\OpenApi\Components\SecuritySchemes\BearerHttpSecurityScheme;
use EugeneErg\OpenApi\Components\SecuritySchemes\HttpSecurityScheme;
use EugeneErg\OpenApi\Components\SecuritySchemes\MutualTlsSecurityScheme;
use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows\Scope;
use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Scheme as Oauth2Scheme;
use EugeneErg\OpenApi\Components\SecuritySchemes\OpenIdConnectSecurityScheme;
use EugeneErg\OpenApi\ExternalDocs;
use EugeneErg\OpenApi\PathItems;
use EugeneErg\OpenApi\Paths;
use EugeneErg\OpenApi\Paths\Operation;
use EugeneErg\OpenApi\Paths\Path;
use EugeneErg\OpenApi\Reference;
use EugeneErg\OpenApi\Securities;
use EugeneErg\OpenApi\Securities\Role;
use EugeneErg\OpenApi\Securities\ScopeName;
use EugeneErg\OpenApi\Servers;
use EugeneErg\OpenApi\Tags;

/**
 * Reads paths, webhooks and callbacks.
 */
final readonly class PathsReader
{
    public function __construct(
        private Registry $registry,
        private ComponentsReader $components,
    ) {
    }

    public function paths(Node $node): ?Paths
    {
        $items = [];

        foreach ($node->extensibleMap() as $template => $item) {
            $items[(string) $template] = $this->reference($item) ?? $this->path($item);
        }

        return $items === [] ? null : Paths::fromArray($items, $node->extensions());
    }

    /**
     * A Callback Object, unlike webhooks and components.pathItems, is extensible.
     */
    public function callback(Node $node): PathItems
    {
        $items = [];

        foreach ($node->extensibleMap() as $name => $item) {
            $items[(string) $name] = $this->reference($item) ?? $this->path($item);
        }

        return PathItems::fromArray($items, $node->extensions());
    }

    public function pathItems(Node $node): PathItems
    {
        $items = [];

        foreach ($node->map() as $name => $item) {
            $items[(string) $name] = $this->reference($item) ?? $this->path($item);
        }

        return PathItems::fromArray($items);
    }

    public function path(Node $node): Path
    {
        $pointer = $this->registry->pointerOfNode($node);

        if ($pointer !== null && $this->registry->has($pointer)) {
            $result = $this->registry->resolve($pointer);

            return $result instanceof Path ? $result : throw $node->unexpected('a path item');
        }

        return $this->buildPath($node);
    }

    public function buildPath(Node $node): Path
    {
        if ($node->has('$ref')) {
            $result = $this->registry->resolve($this->registry->pointerOf($node->get('$ref')->string(), $node));

            return $result instanceof Path ? $result : throw $node->unexpected('a path item');
        }

        return new Path(
            get: $this->method($node, 'get'),
            put: $this->method($node, 'put'),
            post: $this->method($node, 'post'),
            delete: $this->method($node, 'delete'),
            options: $this->method($node, 'options'),
            head: $this->method($node, 'head'),
            patch: $this->method($node, 'patch'),
            trace: $this->method($node, 'trace'),
            servers: $this->servers($node->get('servers')),
            parameters: $this->components->parameters($node->get('parameters')),
            summary: $node->get('summary')->stringOrNull(),
            description: $node->get('description')->stringOrNull(),
            extensions: $node->extensions(),
        );
    }

    public function operation(Node $node): Operation
    {
        $pointer = $this->registry->pointerOfNode($node);

        if ($pointer !== null && $this->registry->has($pointer)) {
            $result = $this->registry->resolve($pointer);

            return $result instanceof Operation ? $result : throw $node->unexpected('an operation');
        }

        return $this->buildOperation($node);
    }

    public function buildOperation(Node $node): Operation
    {
        return new Operation(
            responses: $node->has('responses') ? $this->components->responses($node->get('responses')) : null,
            summary: $node->get('summary')->stringOrNull(),
            description: $node->get('description')->stringOrNull(),
            id: $node->get('operationId')->stringOrNull(),
            deprecated: $node->get('deprecated')->boolOr(false),
            parameters: $this->components->parameters($node->get('parameters')),
            requestBody: $node->has('requestBody')
                ? $this->components->operationRequestBody($node->get('requestBody'))
                : null,
            tags: $this->operationTags($node->get('tags')),
            security: $this->securities($node->get('security')),
            servers: $this->servers($node->get('servers')),
            callbacks: $this->components->callbacks($node->get('callbacks'), $this),
            externalDocs: $this->externalDocs($node->get('externalDocs')),
            extensions: $node->extensions(),
        );
    }

    /**
     * A Security Requirement Object: the name of a scheme plus a list of scopes. The Scope
     * objects come from the scheme itself, so the reference is by identity again.
     */
    public function rootSecurity(Node $node): ?Securities
    {
        return $this->securities($node);
    }

    private function method(Node $node, string $method): ?Operation
    {
        return $node->has($method) ? $this->operation($node->get($method)) : null;
    }

    private function reference(Node $node): ?Reference
    {
        if (!$node->has('$ref')) {
            return null;
        }

        $summary = $node->get('summary')->stringOrNull();
        $description = $node->get('description')->stringOrNull();

        return $summary === null && $description === null
            ? null
            : new Reference($this->path($node), $summary, $description);
    }

    /**
     * In the document an operation's tags are names; here they are the same Tag objects
     * that the top level declares. A tag nobody declared is created on the spot: the
     * specification allows that too.
     */
    private function operationTags(Node $node): ?Tags
    {
        $items = [];

        foreach ($node->strings() as $name) {
            $items[] = $this->registry->tag($name);
        }

        return $items === [] ? null : new Tags(...$items);
    }

    private function securities(Node $node): ?Securities
    {
        // an absent field and an empty list differ: `security: []` on an operation
        // removes the authorisation the document level requires
        if ($node->isMissing()) {
            return null;
        }

        $items = [];

        foreach ($node->list() as $requirement) {
            $scopes = [];

            foreach ($requirement->map() as $name => $list) {
                $scheme = $this->registry->securityScheme((string) $name, $list);

                $names = new Strings(...$list->strings());

                if ($names->items === []) {
                    $scopes[] = $scheme;

                    continue;
                }

                foreach ($names->items as $scopeName) {
                    $scopes[] = $this->scopeOf($scheme, $scopeName, $list);
                }
            }

            // an empty requirement `{}` means something: it allows anonymous access
            $items[] = new Securities\SecuritySchemes(...$scopes);
        }

        return new Securities(...$items);
    }

    private function scopeOf(AbstractSecurityScheme $scheme, string $name, Node $at): Role|Scope|ScopeName
    {
        if ($scheme instanceof OpenIdConnectSecurityScheme) {
            return new ScopeName($scheme, $name);
        }

        if (
            $scheme instanceof ApiKeyScheme
            || $scheme instanceof BasicHttpSecurityScheme
            || $scheme instanceof BearerHttpSecurityScheme
            || $scheme instanceof HttpSecurityScheme
            || $scheme instanceof MutualTlsSecurityScheme
        ) {
            // 3.1 allows listing roles for any scheme; for 3.0 the build rejects it
            return new Role($scheme, $name);
        }

        if (!$scheme instanceof Oauth2Scheme) {
            throw $at->unexpected('a scheme that accepts scope or role names');
        }

        foreach ($scheme->flows->items as $flow) {
            if (isset($flow->scopes->items[$name])) {
                return $flow->scopes->items[$name];
            }
        }

        // the specification does not require a scope to be declared in a flow, so such a document is legal
        return new ScopeName($scheme, $name);
    }

    private function servers(Node $node): ?Servers
    {
        $items = [];

        foreach ($node->list() as $item) {
            $variables = [];

            foreach ($item->get('variables')->map() as $name => $variable) {
                $enum = $variable->get('enum');

                $variables[$name] = new Servers\Variable(
                    default: $variable->get('default')->string(),
                    enum: $enum->isMissing() ? null : new Strings(...$enum->strings()),
                    description: $variable->get('description')->stringOrNull(),
                    extensions: $variable->extensions(),
                );
            }

            $items[] = new Servers\Server(
                url: $item->get('url')->string(),
                description: $item->get('description')->stringOrNull(),
                variables: $variables === [] ? null : Servers\Variables::fromArray($variables),
                extensions: $item->extensions(),
            );
        }

        return $items === [] ? null : new Servers(...$items);
    }

    private function externalDocs(Node $node): ?ExternalDocs
    {
        if ($node->isMissing()) {
            return null;
        }

        return new ExternalDocs(
            url: $node->get('url')->string(),
            description: $node->get('description')->stringOrNull(),
            extensions: $node->extensions(),
        );
    }
}
