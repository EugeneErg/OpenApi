<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Reader;

use EugeneErg\OpenApi\Components\Schemas\String\Strings;
use EugeneErg\OpenApi\Components\SecuritySchemes\AbstractSecurityScheme;
use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows\Scope;
use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Scheme as Oauth2Scheme;
use EugeneErg\OpenApi\Exceptions\InvalidDocumentOpenapiException;
use EugeneErg\OpenApi\ExternalDocs;
use EugeneErg\OpenApi\PathItems;
use EugeneErg\OpenApi\Paths;
use EugeneErg\OpenApi\Paths\Operation;
use EugeneErg\OpenApi\Paths\Path;
use EugeneErg\OpenApi\Reference;
use EugeneErg\OpenApi\Securities;
use EugeneErg\OpenApi\Servers;
use EugeneErg\OpenApi\Tags;

use function sprintf;

/**
 * Разбор paths, webhooks и callbacks.
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

        foreach ($node->map() as $template => $item) {
            $items[(string) $template] = $this->reference($item) ?? $this->path($item);
        }

        return $items === [] ? null : new Paths(...$items);
    }

    public function pathItems(Node $node): PathItems
    {
        $items = [];

        foreach ($node->map() as $name => $item) {
            $items[(string) $name] = $this->reference($item) ?? $this->path($item);
        }

        return new PathItems(...$items);
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
            responses: $this->components->responses($node->get('responses')),
            summary: $node->get('summary')->stringOrNull(),
            description: $node->get('description')->stringOrNull(),
            id: $node->get('operationId')->stringOrNull(),
            deprecated: $node->get('deprecated')->boolOr(false),
            parameters: $this->components->parameters($node->get('parameters')),
            requestBody: $node->has('requestBody') ? $this->components->requestBody($node->get('requestBody')) : null,
            tags: $this->operationTags($node->get('tags')),
            security: $this->securities($node->get('security')),
            servers: $this->servers($node->get('servers')),
            callbacks: $this->components->callbacks($node->get('callbacks'), $this),
            externalDocs: $this->externalDocs($node->get('externalDocs')),
        );
    }

    /**
     * Security Requirement Object: имя схемы плюс список скоупов. Объекты Scope
     * берутся из самой схемы, чтобы ссылка снова была по идентичности.
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
     * В документе теги операции — это имена, а в пакете — те же объекты Tag,
     * что объявлены на верхнем уровне. Незаявленный тег создаётся на месте:
     * спецификация допускает и это.
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

            if ($scopes !== []) {
                $items[] = new Securities\SecuritySchemes(...$scopes);
            }
        }

        return $items === [] ? null : new Securities(...$items);
    }

    private function scopeOf(AbstractSecurityScheme $scheme, string $name, Node $at): Scope
    {
        if (!$scheme instanceof Oauth2Scheme) {
            throw new InvalidDocumentOpenapiException(sprintf(
                '%s: scopes are only meaningful for oauth2 schemes.',
                $at->path,
            ));
        }

        foreach ($scheme->flows->items as $flow) {
            if (isset($flow->scopes->items[$name])) {
                return $flow->scopes->items[$name];
            }
        }

        throw new InvalidDocumentOpenapiException(sprintf(
            '%s: scope "%s" is not declared in any flow of the referenced scheme.',
            $at->path,
            $name,
        ));
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
                );
            }

            $items[] = new Servers\Server(
                url: $item->get('url')->string(),
                description: $item->get('description')->stringOrNull(),
                variables: $variables === [] ? null : new Servers\Variables(...$variables),
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
        );
    }
}
