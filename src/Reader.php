<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchema;
use EugeneErg\OpenApi\Components\Schemas\String\Strings;
use EugeneErg\OpenApi\Components\Schemas\Untyped\Schemas as UntypedSchemas;
use EugeneErg\OpenApi\Exceptions\InvalidDocumentOpenapiException;
use EugeneErg\OpenApi\Reader\ComponentsReader;
use EugeneErg\OpenApi\Reader\Node;
use EugeneErg\OpenApi\Reader\PathsReader;
use EugeneErg\OpenApi\Reader\Reads;
use EugeneErg\OpenApi\Reader\Registry;
use EugeneErg\OpenApi\Reader\SchemaReader;
use EugeneErg\OpenApi\Serialization\DecoderInterface;
use EugeneErg\OpenApi\Serialization\JsonDecoder;

use function array_slice;
use function count;
use function implode;
use function sprintf;
use function strlen;

/**
 * Reads an existing specification back into objects.
 *
 * The inverse of Builder: the same reference yields the same object, so building the
 * document back produces the same `$ref` again.
 *
 * File names are passed as keys, and cross-file references are resolved by them:
 *
 *     Reader::readAll(['openapi.yaml' => $content], new YamlDecoder());
 */
final readonly class Reader
{
    /** The components sections except the schemas: the name in the document and how to read it. */
    private const array SECTIONS = [
        'examples' => 'example',
        'parameters' => 'parameterComponent',
        'headers' => 'header',
        'requestBodies' => 'requestBody',
        'responses' => 'response',
        'links' => 'link',
        'securitySchemes' => 'securityScheme',
    ];

    private const array METHODS = ['get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace'];

    /** How many unread places to name in the message; the rest are only counted. */
    private const int UNREAD_SHOWN = 5;

    /**
     * @param array<string, string> $contents a map of file name => contents
     * @param bool $strict complain about whatever the package did not understand
     *
     * @return array<string, Openapi>
     */
    public static function readAll(array $contents, ?DecoderInterface $decoder = null, bool $strict = true): array
    {
        $decoder ??= new JsonDecoder();
        $documents = [];
        $reads = new Reads();

        foreach ($contents as $fileName => $content) {
            $documents[$fileName] = new Node($decoder->decode($content), $fileName, reads: $reads);
        }

        $reader = new self();
        $registry = new Registry($documents, array_key_first($documents) ?? '');

        // One registry for every file: a schema referred to from a neighbouring document
        // has to be the same object, or the reference unfolds into a copy.
        foreach ($documents as $name => $document) {
            $scoped = $registry->withFile($name);

            $reader->readTags($scoped, $document->get('tags'));
            $reader->declare($scoped, $document, $name);
        }

        $result = [];

        foreach (array_keys($documents) as $fileName) {
            $result[$fileName] = $reader->readDocument($registry->withFile($fileName), $documents, $fileName);
        }

        if ($strict) {
            self::assertEverythingRead($reads, $documents);
        }

        return $result;
    }

    public static function read(string $content, ?DecoderInterface $decoder = null, bool $strict = true): Openapi
    {
        $result = self::readAll(['openapi.json' => $content], $decoder, $strict);

        return $result['openapi.json'] ?? throw new InvalidDocumentOpenapiException('Document could not be read.');
    }

    /**
     * The specification allows an object only the fields it declares and the `x-*`
     * extensions, so anything else would be dropped and would silently disappear when the
     * document is written back. Strict reading names such places; `strict: false` allows
     * them.
     *
     * @param array<string, Node> $documents
     */
    private static function assertEverythingRead(Reads $reads, array $documents): void
    {
        $unread = [];

        foreach ($documents as $document) {
            $unread = [...$unread, ...$reads->unread($document->value, $document->path)];
        }

        if ($unread === []) {
            return;
        }

        $shown = array_slice($unread, 0, self::UNREAD_SHOWN);

        throw new InvalidDocumentOpenapiException(sprintf(
            'The specification does not define %s: %s%s. Pass strict: false to read the document without %s.',
            count($unread) === 1 ? 'this field' : 'these fields',
            implode(', ', $shown),
            count($unread) > count($shown) ? sprintf(' and %d more', count($unread) - count($shown)) : '',
            count($unread) === 1 ? 'it' : 'them',
        ));
    }

    /**
     * @param array<string, Node> $documents
     */
    private function readDocument(Registry $registry, array $documents, string $fileName): Openapi
    {
        $document = $documents[$fileName] ?? throw new InvalidDocumentOpenapiException(
            sprintf('Document "%s" was not passed to the reader.', $fileName),
        );

        $components = new ComponentsReader($registry, new SchemaReader($registry));
        $tags = $this->readTags($registry, $document->get('tags'));
        $paths = new PathsReader($registry, $components);

        return new Openapi(
            info: $this->readInfo($document->get('info')),
            components: $this->readComponents($registry, $components, $paths, $fileName),
            paths: $paths->paths($document->get('paths')),
            servers: $this->readServers($document->get('servers')),
            security: $paths->rootSecurity($document->get('security')),
            tags: $tags === [] ? null : new Tags(...array_values($tags)),
            externalDocs: $this->readExternalDocs($document->get('externalDocs')),
            version: $this->readVersion($document),
            jsonSchemaDialect: $document->get('jsonSchemaDialect')->stringOrNull(),
            webhooks: $document->has('webhooks') ? $paths->pathItems($document->get('webhooks')) : null,
            extensions: $document->extensions(),
        );
    }

    private function declare(Registry $registry, Node $document, string $fileName): void
    {
        $components = $document->get('components');

        foreach ($components->get('schemas')->map() as $name => $node) {
            $this->declareSchema($registry, $fileName . '#/components/schemas/' . self::escape((string) $name), $node);
        }

        foreach (self::SECTIONS as $section => $method) {
            foreach ($components->get($section)->map() as $name => $node) {
                $registry->declare(
                    $fileName . '#/components/' . $section . '/' . self::escape((string) $name),
                    $node,
                    static fn (Node $item): object => self::readSection($registry, $method, $item),
                );
            }
        }

        foreach ($components->get('pathItems')->map() as $name => $node) {
            $this->declarePath($registry, $fileName . '#/components/pathItems/' . self::escape((string) $name), $node);
        }

        // A Callback Object is a map of expressions rather than a single object, so it is
        // declared here instead of among SECTIONS: the paths reader builds it
        foreach ($components->get('callbacks')->map() as $name => $node) {
            $pointer = $fileName . '#/components/callbacks/' . self::escape((string) $name);

            $registry->declare(
                $pointer,
                $node,
                static fn (Node $item): object => self::pathsReader($registry)->callback($item),
            );

            $this->declareCallback($registry, $pointer, $node);
        }

        foreach ($document->get('paths')->map() as $template => $node) {
            $this->declarePath($registry, $fileName . '#/paths/' . self::escape((string) $template), $node);
        }

        // A Path Item Object lives in four places, and an operation in any of them is an
        // operation of this document: a Link may name it, and `operationId` "MUST be
        // resolved within the scope of the OpenAPI Description".
        foreach ($document->get('webhooks')->map() as $name => $node) {
            $this->declarePath($registry, $fileName . '#/webhooks/' . self::escape((string) $name), $node);
        }
    }

    /**
     * The Path Items of a Callback Object: their operations carry ids of their own.
     */
    private function declareCallback(Registry $registry, string $pointer, Node $node): void
    {
        foreach ($node->extensibleMap() as $expression => $item) {
            $this->declarePath($registry, $pointer . '/' . self::escape((string) $expression), $item);
        }
    }

    private function declarePath(Registry $registry, string $pointer, Node $node): void
    {
        $registry->declare(
            $pointer,
            $node,
            static fn (Node $item): object => self::pathsReader($registry)->buildPath($item),
        );

        foreach (self::METHODS as $method) {
            if (!$node->has($method)) {
                continue;
            }

            $operationPointer = $pointer . '/' . $method;
            $operationNode = $node->get($method);

            $registry->declare(
                $operationPointer,
                $operationNode,
                static fn (Node $item): object => self::pathsReader($registry)->buildOperation($item),
            );

            $id = $operationNode->get('operationId')->stringOrNull();

            if ($id !== null) {
                $registry->declareOperationId($id, $operationPointer);
            }

            // an operation's callbacks hold Path Items of their own, down to any depth
            foreach ($operationNode->get('callbacks')->extensibleMap() as $name => $callback) {
                $this->declareCallback(
                    $registry,
                    $operationPointer . '/callbacks/' . self::escape((string) $name),
                    $callback,
                );
            }
        }
    }

    private function declareSchema(Registry $registry, string $pointer, Node $node): void
    {
        $registry->declare(
            $pointer,
            $node,
            static fn (Node $item): AbstractSchema => (new SchemaReader($registry))->readComponentSchema($item),
        );

        foreach ($node->get('$defs')->map() as $name => $child) {
            $this->declareSchema($registry, $pointer . '/$defs/' . self::escape((string) $name), $child);
        }
    }

    private static function readSection(Registry $registry, string $method, Node $node): object
    {
        $reader = new ComponentsReader($registry, new SchemaReader($registry));

        return match ($method) {
            'example' => $reader->buildExample($node),
            'parameterComponent' => $reader->buildParameterComponent($node),
            'header' => $reader->buildHeader($node),
            'requestBody' => $reader->buildRequestBody($node),
            'response' => $reader->buildResponse($node),
            'link' => $reader->buildLink($node),
            default => $reader->buildSecurityScheme($node),
        };
    }

    private static function pathsReader(Registry $registry): PathsReader
    {
        return new PathsReader($registry, new ComponentsReader($registry, new SchemaReader($registry)));
    }

    private function readComponents(
        Registry $registry,
        ComponentsReader $components,
        PathsReader $paths,
        string $fileName,
    ): Components {
        $node = $registry->document($fileName)->get('components');

        return new Components(
            examples: $components->examples($node->get('examples')),
            pathItems: $node->has('pathItems') ? $paths->pathItems($node->get('pathItems')) : null,
            schemas: $this->readComponentSchemas($registry, $fileName),
            parameters: $components->parameterComponents($node->get('parameters')),
            headers: $components->headers($node->get('headers')),
            requestBodies: $components->requestBodies($node->get('requestBodies')),
            responses: $node->has('responses') ? $components->responses($node->get('responses')) : null,
            securitySchemes: $components->securitySchemes($node->get('securitySchemes')),
            links: $components->links($node->get('links')),
            callbacks: $components->callbacks($node->get('callbacks'), $paths),
            extensions: $node->extensions(),
        );
    }

    private function readComponentSchemas(Registry $registry, string $fileName): ?UntypedSchemas
    {
        $prefix = $fileName . '#/components/schemas/';
        $items = [];

        foreach ($registry->pointers($prefix) as $pointer) {
            $name = substr($pointer, strlen($prefix));

            // nested $defs belong inside their own schema, not in components
            if (str_contains($name, '/')) {
                continue;
            }

            $schema = $registry->resolve($pointer);

            $items[$name] = $schema instanceof AbstractSchema
                ? $schema
                : throw new InvalidDocumentOpenapiException(sprintf('"%s" is not a schema.', $pointer));
        }

        return $items === [] ? null : UntypedSchemas::fromArray($items);
    }

    private function readVersion(Node $document): Version
    {
        $value = $document->get('openapi')->string();

        return Version::tryFrom($value) ?? throw $document->get('openapi')->unexpected(
            'a supported OpenAPI version (3.0.0-3.0.4, 3.1.0, 3.1.1)',
        );
    }

    private function readInfo(Node $node): Info
    {
        if ($node->isMissing()) {
            throw $node->unexpected('an info object');
        }

        $contact = $node->get('contact');
        $license = $node->get('license');

        return new Info(
            title: $node->get('title')->string(),
            version: $node->get('version')->string(),
            summary: $node->get('summary')->stringOrNull(),
            description: $node->get('description')->stringOrNull(),
            termsOfService: $node->get('termsOfService')->stringOrNull(),
            contact: $contact->isMissing() ? null : new Info\Contact(
                name: $contact->get('name')->stringOrNull(),
                url: $contact->get('url')->stringOrNull(),
                email: $contact->get('email')->stringOrNull(),
                extensions: $contact->extensions(),
            ),
            license: $license->isMissing() ? null : new Info\License(
                name: $license->get('name')->string(),
                url: $license->get('url')->stringOrNull(),
                identifier: $license->get('identifier')->stringOrNull(),
                extensions: $license->extensions(),
            ),
            extensions: $node->extensions(),
        );
    }

    private function readServers(Node $node): ?Servers
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

    /**
     * @return array<string, Tags\Tag>
     */
    private function readTags(Registry $registry, Node $node): array
    {
        $items = [];

        foreach ($node->list() as $item) {
            $name = $item->get('name')->string();

            $registry->declareTag($name, new Tags\Tag(
                name: $name,
                description: $item->get('description')->stringOrNull(),
                externalDocs: $this->readExternalDocs($item->get('externalDocs')),
                extensions: $item->extensions(),
            ));

            $items[$name] = $registry->tag($name);
        }

        return $items;
    }

    private function readExternalDocs(Node $node): ?ExternalDocs
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

    private static function escape(string $value): string
    {
        return str_replace(['~', '/'], ['~0', '~1'], $value);
    }
}
