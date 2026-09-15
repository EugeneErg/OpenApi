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
use EugeneErg\OpenApi\Reader\Registry;
use EugeneErg\OpenApi\Reader\SchemaReader;
use EugeneErg\OpenApi\Serialization\DecoderInterface;
use EugeneErg\OpenApi\Serialization\JsonDecoder;

use function sprintf;
use function strlen;

/**
 * Разбор готовой спецификации обратно в объекты.
 *
 * Обратная задача к Builder: одна и та же ссылка даёт один и тот же объект,
 * поэтому собранный обратно документ снова даст те же `$ref`.
 *
 * Имена файлов передаются ключами — ими же разрешаются кросс-файловые ссылки:
 *
 *     Reader::readAll(['openapi.yaml' => $content], new YamlDecoder());
 */
final readonly class Reader
{
    /** Секции components, кроме схем: имя в документе и способ разбора. */
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

    /**
     * @param array<string, string> $contents карта «имя файла => содержимое»
     *
     * @return array<string, Openapi>
     */
    public static function readAll(array $contents, ?DecoderInterface $decoder = null): array
    {
        $decoder ??= new JsonDecoder();
        $documents = [];

        foreach ($contents as $fileName => $content) {
            $documents[$fileName] = new Node($decoder->decode($content), $fileName);
        }

        $reader = new self();
        $registry = new Registry($documents, array_key_first($documents) ?? '');

        // Реестр один на все файлы: схема, на которую ссылаются из соседнего
        // документа, обязана быть тем же объектом, иначе ссылка развернётся копией.
        foreach ($documents as $name => $document) {
            $scoped = $registry->withFile($name);

            $reader->readTags($scoped, $document->get('tags'));
            $reader->declare($scoped, $document, $name);
        }

        $result = [];

        foreach (array_keys($documents) as $fileName) {
            $result[$fileName] = $reader->readDocument($registry->withFile($fileName), $documents, $fileName);
        }

        return $result;
    }

    public static function read(string $content, ?DecoderInterface $decoder = null): Openapi
    {
        $result = self::readAll(['openapi.json' => $content], $decoder);

        return $result['openapi.json'] ?? throw new InvalidDocumentOpenapiException('Document could not be read.');
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
        );
    }

    private function declare(Registry $registry, Node $document, string $fileName): void
    {
        $components = $document->get('components');

        foreach ($components->get('schemas')->map() as $name => $node) {
            $this->declareSchema($registry, $fileName . '#/components/schemas/' . $name, $node);
        }

        foreach (self::SECTIONS as $section => $method) {
            foreach ($components->get($section)->map() as $name => $node) {
                $registry->declare(
                    $fileName . '#/components/' . $section . '/' . $name,
                    $node,
                    static fn (Node $item): object => self::readSection($registry, $method, $item),
                );
            }
        }

        foreach ($components->get('pathItems')->map() as $name => $node) {
            $this->declarePath($registry, $fileName . '#/components/pathItems/' . $name, $node);
        }

        foreach ($document->get('paths')->map() as $template => $node) {
            $this->declarePath($registry, $fileName . '#/paths/' . self::escape((string) $template), $node);
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
        }
    }

    private function declareSchema(Registry $registry, string $pointer, Node $node): void
    {
        $registry->declare(
            $pointer,
            $node,
            static fn (Node $item): AbstractSchema => (new SchemaReader($registry))->readSchema($item),
        );

        foreach ($node->get('$defs')->map() as $name => $child) {
            $this->declareSchema($registry, $pointer . '/$defs/' . $name, $child);
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
        );
    }

    private function readComponentSchemas(Registry $registry, string $fileName): ?UntypedSchemas
    {
        $prefix = $fileName . '#/components/schemas/';
        $items = [];

        foreach ($registry->pointers($prefix) as $pointer) {
            $name = substr($pointer, strlen($prefix));

            // вложенные $defs попадут внутрь своей схемы, а не в components
            if (str_contains($name, '/')) {
                continue;
            }

            $schema = $registry->resolve($pointer);

            $items[$name] = $schema instanceof AbstractSchema
                ? $schema
                : throw new InvalidDocumentOpenapiException(sprintf('"%s" is not a schema.', $pointer));
        }

        return $items === [] ? null : new UntypedSchemas(...$items);
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
            ),
            license: $license->isMissing() ? null : new Info\License(
                name: $license->get('name')->string(),
                url: $license->get('url')->stringOrNull(),
                identifier: $license->get('identifier')->stringOrNull(),
            ),
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
        );
    }

    private static function escape(string $value): string
    {
        return str_replace(['~', '/'], ['~0', '~1'], $value);
    }
}
