<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Reader;

use Closure;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\DeferredSchema;
use EugeneErg\OpenApi\Components\SecuritySchemes\AbstractSecurityScheme;
use EugeneErg\OpenApi\Exceptions\InvalidDocumentOpenapiException;
use EugeneErg\OpenApi\Paths\DeferredOperation;
use EugeneErg\OpenApi\Paths\Operation;
use EugeneErg\OpenApi\Tags\Tag;
use stdClass;

use function sprintf;
use function strlen;

/**
 * Resolves $ref into objects.
 *
 * The one requirement that matters: two references to the same pointer must yield the
 * **same** instance. All deduplication in the package rests on that, so the result is
 * remembered and reused.
 *
 * Cycles are possible among schemas only — a recursive tree is an everyday thing — and
 * there a DeferredSchema is returned. A cycle among any other components means a broken
 * document, and it is reported as one.
 */
final class Registry
{
    private readonly References $references;

    /**
     * @param array<string, Node> $documents a map of file name => the document's root
     */
    public function __construct(
        private readonly array $documents,
        private string $currentFile,
        ?References $references = null,
    ) {
        $this->references = $references ?? new References();
    }

    public function withFile(string $fileName): self
    {
        $clone = clone $this;
        $clone->currentFile = $fileName;

        return $clone;
    }

    /**
     * In 3.1 a `$ref` in a schema is an ordinary keyword; in 3.0 its siblings do not apply.
     */
    public function isV31(): bool
    {
        $version = ($this->documents[$this->currentFile] ?? new Node(null))->get('openapi')->stringOrNull();

        return $version !== null && str_starts_with($version, '3.1.');
    }

    public function currentFile(): string
    {
        return $this->currentFile;
    }

    /**
     * Registers how to build the object a pointer addresses. The object itself is not
     * built until somebody refers to it, or until its turn comes.
     *
     * @param Closure(Node): object $factory
     */
    public function declare(string $pointer, Node $node, Closure $factory): void
    {
        $this->references->nodes[$pointer] = $node;
        $this->references->factories[$pointer] = $factory;
    }

    public function declareOperationId(string $operationId, string $pointer): void
    {
        $this->references->operationIds[$operationId] = $pointer;
    }

    /**
     * The target of a Link: by operationId or by operationRef.
     *
     * An operation is an ordinary object rather than a deferred reference, so two
     * operations pointing at each other cannot be expressed; such a cycle is reported.
     */
    public function operation(Node $link): DeferredOperation|Operation
    {
        $id = $link->get('operationId')->stringOrNull();
        $ref = $link->get('operationRef')->stringOrNull();

        if ($id !== null) {
            $pointer = $this->references->operationIds[$id]
                ?? throw $link->get('operationId')->unexpected(sprintf('a declared operationId, got "%s"', $id));
        } elseif ($ref !== null) {
            $pointer = $this->pointerOf($ref, $link);
        } else {
            throw $link->unexpected('either operationId or operationRef');
        }

        // an operation may point at itself: that is how pagination is described
        if (isset($this->references->building[$pointer])) {
            return new DeferredOperation(fn (): Operation => $this->builtOperation($pointer));
        }

        return $this->builtOperation($pointer);
    }

    public function declareTag(string $name, Tag $tag): void
    {
        $this->references->tags[$name] = $tag;
    }

    /**
     * An operation's tag is a name; the object is the one the top level declares. A tag
     * nobody declared is created on the spot: the specification allows that too.
     */
    public function tag(string $name): Tag
    {
        return $this->references->tags[$name] ??= new Tag(name: $name);
    }

    public function securityScheme(string $name, Node $at): AbstractSecurityScheme
    {
        $pointer = $this->currentFile . '#/components/securitySchemes/' . $name;

        if (!$this->has($pointer)) {
            // the only thing the specification requires of a Security Requirement;
            // the message speaks of the scheme itself, not of the type of the value beside it
            throw new InvalidDocumentOpenapiException(sprintf(
                '%s: security scheme "%s" is not declared in components.securitySchemes.',
                $at->path === '' ? 'Document root' : $at->path,
                $name,
            ));
        }

        $result = $this->resolve($pointer);

        return $result instanceof AbstractSecurityScheme
            ? $result
            : throw $at->unexpected(sprintf('a security scheme named "%s"', $name));
    }

    public function has(string $pointer): bool
    {
        return isset($this->references->factories[$pointer]);
    }

    /**
     * @return list<string>
     */
    public function pointers(string $prefix): array
    {
        return array_values(array_filter(
            array_keys($this->references->factories),
            static fn (string $pointer): bool => str_starts_with($pointer, $prefix),
        ));
    }

    public function resolve(string $pointer): object
    {
        if (isset($this->references->resolved[$pointer])) {
            return $this->references->resolved[$pointer];
        }

        if (!isset($this->references->factories[$pointer])) {
            throw new InvalidDocumentOpenapiException(sprintf(
                'Reference "%s" does not point at anything in the document.',
                $pointer,
            ));
        }

        if (isset($this->references->building[$pointer])) {
            throw new InvalidDocumentOpenapiException(sprintf(
                'Reference "%s" is part of a cycle that only schemas may form.',
                $pointer,
            ));
        }

        $this->references->building[$pointer] = true;

        try {
            $node = $this->references->nodes[$pointer] ?? throw new InvalidDocumentOpenapiException(
                sprintf('Reference "%s" has no node.', $pointer),
            );
            $result = ($this->references->factories[$pointer])($node);
        } finally {
            unset($this->references->building[$pointer]);
        }

        return $this->references->resolved[$pointer] = $result;
    }

    /**
     * The schema a pointer addresses. If it is being built right now, that is a cycle and
     * a deferred reference is returned: otherwise the object would have to be passed to its
     * own constructor.
     */
    public function resolveSchema(string $pointer): AbstractSchema
    {
        if (isset($this->references->building[$pointer])) {
            return new DeferredSchema(fn (): AbstractSchema => $this->schema($pointer));
        }

        return $this->schema($pointer);
    }

    /**
     * The pointer a $ref string denotes: `#/components/schemas/User` or
     * `other.yaml#/components/schemas/User`.
     */
    public function pointerOf(string $ref, Node $at): string
    {
        $position = strpos($ref, '#');

        if ($position === false) {
            throw new InvalidDocumentOpenapiException(sprintf(
                '%s: reference "%s" must contain a fragment; whole-document references are not supported.',
                $at->path,
                $ref,
            ));
        }

        $file = substr($ref, 0, $position);
        $fragment = substr($ref, $position + 1);

        if ($file !== '' && !isset($this->documents[$file])) {
            throw new InvalidDocumentOpenapiException(sprintf(
                '%s: reference "%s" points at a file that was not passed to the reader.',
                $at->path,
                $ref,
            ));
        }

        return ($file === '' ? $this->currentFile : $file) . '#' . $fragment;
    }

    /**
     * The pointer that matches a node: a node's path starts with the file name, while a
     * pointer has a "#" in that place.
     */
    public function pointerOfNode(Node $node): ?string
    {
        foreach (array_keys($this->documents) as $fileName) {
            if ($node->path === $fileName) {
                return $fileName . '#';
            }

            if (str_starts_with($node->path, $fileName . '/')) {
                return $fileName . '#' . substr($node->path, strlen($fileName));
            }
        }

        return null;
    }

    public function fileOf(string $pointer): string
    {
        $position = strpos($pointer, '#');

        return $position === false ? $pointer : substr($pointer, 0, $position);
    }

    /**
     * The node a pointer addresses; needed to declare components while walking the document.
     */
    public function node(string $pointer): ?Node
    {
        return $this->references->nodes[$pointer] ?? null;
    }

    /**
     * @return array<string, Node>
     */
    public function documents(): array
    {
        return $this->documents;
    }

    public function document(string $fileName): Node
    {
        return $this->documents[$fileName] ?? new Node(new stdClass(), $fileName);
    }

    private function builtOperation(string $pointer): Operation
    {
        $result = $this->resolve($pointer);

        return $result instanceof Operation
            ? $result
            : throw new InvalidDocumentOpenapiException(sprintf('"%s" is not an operation.', $pointer));
    }

    private function schema(string $pointer): AbstractSchema
    {
        $result = $this->resolve($pointer);

        if (!$result instanceof AbstractSchema) {
            throw new InvalidDocumentOpenapiException(sprintf(
                'Reference "%s" points at %s, but a schema was expected.',
                $pointer,
                $result::class,
            ));
        }

        return $result;
    }
}
