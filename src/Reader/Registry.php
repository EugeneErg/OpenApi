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
 * Разрешение $ref в объекты.
 *
 * Ключевое требование: две ссылки на один указатель обязаны дать **один и тот же**
 * экземпляр. На этом держится вся дедупликация пакета, поэтому результат
 * запоминается и переиспользуется.
 *
 * Циклы возможны только среди схем (рекурсивное дерево — обычное дело), и там
 * возвращается DeferredSchema. Цикл среди прочих компонентов означает испорченный
 * документ, и о нём сообщается явно.
 */
final class Registry
{
    private readonly References $references;

    /**
     * @param array<string, Node> $documents карта «имя файла => корень документа»
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
     * В 3.1 `$ref` в схеме — обычное ключевое слово, в 3.0 соседи `$ref` не действуют.
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
     * Регистрирует способ построить объект по указателю. Сам объект не строится,
     * пока на него кто-нибудь не сошлётся или пока не дойдёт очередь.
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
     * Цель ссылки Link: по operationId либо по operationRef.
     *
     * Операция — обычный объект, а не отложенная ссылка, поэтому взаимные ссылки
     * между двумя операциями представить нельзя; о таком цикле сообщается явно.
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

        // операция может ссылаться на саму себя — так описана пагинация
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
     * Тег операции — это имя; объект берётся тот же, что объявлен на верхнем уровне.
     * Незаявленный тег создаётся на месте: спецификация допускает и это.
     */
    public function tag(string $name): Tag
    {
        return $this->references->tags[$name] ??= new Tag(name: $name);
    }

    public function securityScheme(string $name, Node $at): AbstractSecurityScheme
    {
        $pointer = $this->currentFile . '#/components/securitySchemes/' . $name;

        if (!$this->has($pointer)) {
            // единственное, чего спецификация требует от Security Requirement:
            // сообщение говорит о самой схеме, а не о типе значения рядом с ней
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
     * Схема по указателю. Если она сейчас строится — цикл, и возвращается
     * отложенная ссылка: иначе объект пришлось бы передать в собственный конструктор.
     */
    public function resolveSchema(string $pointer): AbstractSchema
    {
        if (isset($this->references->building[$pointer])) {
            return new DeferredSchema(fn (): AbstractSchema => $this->schema($pointer));
        }

        return $this->schema($pointer);
    }

    /**
     * Указатель из строки $ref: `#/components/schemas/User` либо
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
     * Указатель, соответствующий узлу: путь узла начинается с имени файла,
     * а в указателе на этом месте стоит «#».
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
     * Узел по указателю — нужен, чтобы объявлять компоненты по мере обхода документа.
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
