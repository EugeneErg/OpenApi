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
 * The context in which one document is built.
 *
 * Every toObject() is handed a Process and asks it: is this object already sitting in
 * some components? If it is, a $ref stands in its place — local, or carrying the name of
 * a neighbouring file. If it is not, the object is written out where it is used.
 */
final readonly class Process
{
    public function __construct(
        public Builder $builder,
        public Openapi $openapi,
        /**
         * Print the values that equal their default. The document does not change because
         * of it: the verbose form is the same specification, written out in full.
         */
        public bool $verbose = false,
    ) {
    }

    public function version(): Version
    {
        return $this->openapi->version;
    }

    /**
     * The things that appeared in 3.1 only: the JSON Schema 2020-12 vocabulary (a 3.0
     * schema is a trimmed Draft 4) and the Reference Object's own fields.
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
     * The single place where a reference is resolved: the kind of target picks the lookup.
     * Used by Reference Object; there is no separate, "manual" $ref in the package.
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
            // a ContentParameter does not know its own in: that comes from where it is
            // used, so as a component such a parameter is addressed through headers only
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
        // a deferred reference from a recursion points at the same registered schema
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

    /**
     * The pointer a Link writes as `operationRef`. The section is part of what the
     * document says, because a Path Item Object lives in four of them: `paths`,
     * `webhooks`, `components.pathItems` and a Callback Object.
     */
    public function findOperation(Paths\Operation $value): string
    {
        return $this->operationPointer($value) ?? throw new OperationNotFoundOpenapiException(
            'Operation is not registered in any document passed to the Builder.',
        );
    }

    /**
     * A Link may name its target by `operationId` instead of a pointer, and then nothing
     * in the written document says where that operation is. The specification requires
     * the id to be resolvable, so it is checked here: otherwise the build would quietly
     * write a link that leads nowhere.
     */
    public function assertOperation(Paths\Operation $value): void
    {
        if ($this->operationPointer($value) === null) {
            throw new OperationNotFoundOpenapiException(sprintf(
                'Operation "%s" is not registered in any document passed to the Builder.',
                $value->id ?? '',
            ));
        }
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
     * A Security Requirement Object refers to the securitySchemes of its own document, so
     * the scope and the scheme are looked up without reaching the neighbouring files.
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
     * The file whose document declares a schema as a component: '' for the document being
     * built, and null when no document does.
     *
     * A dynamic anchor is found by name rather than by pointer, and a name only reaches
     * across a file boundary when the reference carries the file.
     */
    public function fileOfSchema(AbstractSchema $schema): ?string
    {
        if ($this->openapi->findSchema($schema) !== null) {
            return '';
        }

        foreach ($this->builder->openapi as $fileName => $item) {
            if ($item !== $this->openapi && $item->findSchema($schema) !== null) {
                return $fileName;
            }
        }

        return null;
    }

    private function operationPointer(Paths\Operation $value): ?string
    {
        return $this->toPointer(static fn (Openapi $openapi) => $openapi->findOperation($value), '');
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
     * The current document first (a local reference), then the rest (a reference carrying
     * a file name).
     *
     * @param callable(Openapi): ?string $callback
     */
    private function toPointer(callable $callback, string $component): ?string
    {
        // an empty section means the callback returns the whole path: an operation names
        // its own section, because a Path Item Object lives in several of them
        $prefix = $component === '' ? '#/' : '#/' . $component . '/';
        $result = $callback($this->openapi);

        if ($result !== null) {
            return $prefix . $result;
        }

        foreach ($this->builder->openapi as $fileName => $item) {
            if ($item !== $this->openapi) {
                $result = $callback($item);

                if ($result !== null) {
                    return $fileName . $prefix . $result;
                }
            }
        }

        return null;
    }

    /**
     * A reference to a whole components section: either it is this document's own (and
     * then it is written out in place), or it belongs to a document declared before this
     * one.
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
