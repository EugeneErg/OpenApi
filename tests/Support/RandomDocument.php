<?php

declare(strict_types = 1);

namespace Tests\Support;

use EugeneErg\OpenApi\Components;
use EugeneErg\OpenApi\Components\Examples;
use EugeneErg\OpenApi\Components\Examples\Example;
use EugeneErg\OpenApi\Components\Links;
use EugeneErg\OpenApi\Components\Links\Link;
use EugeneErg\OpenApi\Components\Parameters;
use EugeneErg\OpenApi\Components\RequestBodies;
use EugeneErg\OpenApi\Components\Responses;
use EugeneErg\OpenApi\Components\Schemas;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchema;
use EugeneErg\OpenApi\Components\SecuritySchemes;
use EugeneErg\OpenApi\Components\SecuritySchemes\AbstractSecurityScheme;
use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security;
use EugeneErg\OpenApi\Extensions;
use EugeneErg\OpenApi\ExternalDocs;
use EugeneErg\OpenApi\Info;
use EugeneErg\OpenApi\Openapi;
use EugeneErg\OpenApi\PathItems;
use EugeneErg\OpenApi\Paths;
use EugeneErg\OpenApi\Paths\DeferredOperation;
use EugeneErg\OpenApi\Paths\Operation;
use EugeneErg\OpenApi\Reference;
use EugeneErg\OpenApi\Securities;
use EugeneErg\OpenApi\Servers;
use EugeneErg\OpenApi\Tags;
use EugeneErg\OpenApi\Version;
use LogicException;

use function count;
use function sprintf;

/**
 * A document nobody wrote: the objects of the package combined at random.
 *
 * Every document in tests/Cases was written by hand, so it exercises what somebody thought
 * of. This one exercises combinations nobody would think of — an enumeration under a
 * `not`, a reused parameter inside a callback — and that is where the round trip breaks.
 *
 * Reproducibility matters more than randomness: the generator carries its own xorshift,
 * because `mt_rand` is free to change between PHP versions, and a failure that cannot be
 * repeated by its seed is not a failure anybody can fix.
 *
 * Whatever this class builds is valid by construction: the constructors check the
 * invariants, so a document that fails to build here is a mistake of the generator rather
 * than of the package — and the test says so.
 */
final class RandomDocument
{
    /**
     * Names come from a dictionary rather than from random letters: a failing document is
     * read by a human, and `/orders/{orderId}` is read faster than `/a7f/{b2}`.
     */
    private const array WORDS = [
        'user', 'order', 'payment', 'item', 'note', 'file', 'token', 'page',
        'cursor', 'status', 'kind', 'region', 'level', 'tag', 'card', 'session',
    ];

    private const array MIME_TYPES = [
        'application/json', 'text/plain', 'application/xml', 'multipart/form-data',
    ];

    private int $state;

    private int $counter = 0;

    /**
     * The schemas that will be registered in components: reusing one of these is what
     * makes the build print a `$ref`, so the pool is what the deduplication is tested on.
     *
     * @var list<AbstractSchema>
     */
    private array $pool = [];

    /**
     * Which document declares each pooled schema: the others refer to it across the file
     * boundary, which is the only way `doc1.json#/components/schemas/X` ever appears.
     *
     * @var array<int, int>
     */
    private array $owners = [];

    /** @var array<string, Operation> */
    private array $operations = [];

    /**
     * The operations without an operationId: a Link names those by a pointer to their
     * place, which is a different branch of the same code.
     *
     * @var list<Operation>
     */
    private array $anonymous = [];

    /**
     * Security schemes belong to one document: a Security Requirement Object refers to the
     * schemes of its own document, so these are made anew for every document rather than
     * shared like the rest.
     *
     * @var array<string, AbstractSecurityScheme>
     */
    private array $schemes = [];

    /** @var list<Oauth2Security\Flows\Scope> */
    private array $scopes = [];

    private int $current = 0;

    /**
     * The objects registered in components. A `$ref` needs a target that is addressable,
     * and the same object used at two places is the only way this package writes one, so
     * everything shared lives here and is reused at random.
     */
    private Responses\Response $sharedResponse;

    private Parameters\Parameter $sharedParameter;

    private Parameters\Query\SchemaParameter $sharedQuery;

    /**
     * A parameter of every location, registered in components.parameters: read back, a
     * `$ref` in a header, cookie or path position has to land in the right container, and
     * its name has to come from the registration rather than from the place of use.
     */
    private Parameters\Header\SchemaParameter $sharedHeaderParameter;

    private Parameters\Cookie\SchemaParameter $sharedCookieParameter;

    private Parameters\Path\SchemaParameter $sharedPathParameter;

    private string $sharedHeaderParameterName = 'X-Shared-Parameter';

    private string $sharedCookieParameterName = 'shared';

    private string $sharedPathName = 'id';

    /**
     * The schemas container of the first document. A neighbour may hand the very same
     * container to its own Components, and then the reference is to the whole section —
     * `doc0.json#/components/schemas` — rather than to an item of it.
     */
    private ?Schemas\Untyped\Schemas $sharedSchemas = null;

    private RequestBodies\RequestBody $sharedRequestBody;

    private Example $sharedExample;

    /** A Header Object of components.headers, as opposed to the parameter above. */
    private Parameters\Header\SchemaParameter $sharedHeaderComponent;

    private string $sharedHeaderComponentName = 'X-Shared-Header';

    public function __construct(int $seed, private readonly Version $version)
    {
        // xorshift needs a non-zero state, and neighbouring seeds should not give
        // neighbouring documents
        $this->state = ($seed * 2_654_435_761) % 0xFFFFFFFF ?: 1;

        // placeholders, so the class never carries half-built state; prepare() replaces
        // every one of them with something random before a document is made
        $this->sharedQuery = new Parameters\Query\SchemaParameter(schema: new Schemas\String\Schema());
        $this->sharedParameter = new Parameters\Parameter(name: 'shared', parameter: $this->sharedQuery);
        $this->sharedExample = new Example(value: new Schemas\Untyped\Value('shared'));
        $this->sharedHeaderComponent = new Parameters\Header\SchemaParameter(schema: new Schemas\String\Schema());
        $this->sharedHeaderComponentName = 'X-Shared';
        $this->sharedRequestBody = new RequestBodies\RequestBody(
            content: RequestBodies\Contents::fromArray([
                'text/plain' => new RequestBodies\Content(schema: new Schemas\String\Schema()),
            ]),
        );
        $this->sharedResponse = new Responses\Response(description: 'Shared.');
        $this->sharedHeaderParameter = new Parameters\Header\SchemaParameter(schema: new Schemas\String\Schema());
        $this->sharedCookieParameter = new Parameters\Cookie\SchemaParameter(schema: new Schemas\String\Schema());
        $this->sharedPathParameter = new Parameters\Path\SchemaParameter(schema: new Schemas\String\Schema());
    }

    public function build(): Openapi
    {
        foreach ($this->buildAll(1) as $document) {
            return $document;
        }

        throw new LogicException('buildAll(1) returns exactly one document.');
    }

    /**
     * Documents of different versions in one build.
     *
     * A reference across a version boundary is refused, so every document here is built by
     * a generator of its own: separate pools, separate shared components, nothing to point
     * at across the boundary. What this exercises is the rest of it — one Builder writing
     * 3.0 and 3.1 side by side.
     *
     * @return array<string, Openapi>
     */
    public static function mixedVersions(int $seed, int $count): array
    {
        $result = [];

        for ($i = 0; $i < $count; ++$i) {
            $version = $i % 2 === 0 ? Version::V303 : Version::V311;
            $result[sprintf('mixed%d.json', $i)] = (new self($seed * 31 + $i, $version))->build();
        }

        return $result;
    }

    /**
     * Several documents that refer to each other: the pooled schemas are declared in one
     * of them and used in all of them, so the cross-file `$ref` is exercised as well.
     *
     * @return array<string, Openapi>
     */
    public function buildAll(int $count): array
    {
        $this->prepare($count);

        $result = [];

        for ($i = 0; $i < $count; ++$i) {
            $this->current = $i;
            $result[sprintf('doc%d.json', $i)] = $this->document();
        }

        return $result;
    }

    private function prepare(int $count): void
    {
        $this->pool = [];
        $this->owners = [];
        $this->sharedSchemas = null;

        for ($i = $this->int(2, 6); $i > 0; --$i) {
            $this->pool[] = $this->schema(2);
            $this->owners[] = $this->int(0, $count - 1);
        }

        // a discriminator names its branches, and the names have to be registered
        // components: the two branches and the union all go into the pool together
        $first = $this->variant('first');
        $second = $this->variant('second');
        $this->add($first, $count);
        $this->add($second, $count);
        $this->add(new Schemas\Untyped\Schema(
            oneOf: new Schemas\Untyped\Schemas($first, $second),
            discriminator: new Schemas\Abstract\Discriminator(
                propertyName: 'kind',
                mapping: new Schemas\Untyped\Schemas(first: $first, second: $second),
                extensions: $this->isV31() ? $this->extensions() : null,
            ),
        ), $count);

        if ($this->isV31()) {
            // a schema that is a resource of its own, a schema addressed inside its
            // $defs, and a dynamic anchor with a reference to it
            $inner = new Schemas\String\Schema(minLength: 1);
            $anchored = new Schemas\Object\Schema(
                properties: new Schemas\Object\Properties(
                    value: new Schemas\Object\Property(schema: $inner),
                ),
                resource: new Schemas\Abstract\Resource(
                    id: 'https://example.com/schemas/' . $this->name(),
                    schema: 'https://json-schema.org/draft/2020-12/schema',
                    anchor: 'anchor' . $this->counter++,
                    dynamicAnchor: 'node' . $this->counter++,
                    defs: new Schemas\Untyped\Schemas(Inner: $inner),
                    comment: $this->sentence(),
                ),
            );

            $this->add($anchored, $count);
            $this->add($inner, $count);
            $this->add(new Schemas\Untyped\Schema(
                resource: new Schemas\Abstract\Resource(dynamicRef: $anchored),
            ), $count);
        }

        $this->current = 0;
        $this->sharedQuery = new Parameters\Query\SchemaParameter(schema: $this->schema(1));
        $this->sharedParameter = new Parameters\Parameter(name: $this->name(), parameter: $this->sharedQuery);
        $this->sharedPathName = $this->name();
        $this->sharedHeaderParameterName = $this->header();
        $this->sharedCookieParameterName = $this->name();
        $this->sharedHeaderParameter = new Parameters\Header\SchemaParameter(schema: $this->schema(1));
        $this->sharedCookieParameter = new Parameters\Cookie\SchemaParameter(schema: $this->schema(1));
        $this->sharedPathParameter = new Parameters\Path\SchemaParameter(schema: $this->schema(1));
        // the order matters: the later ones reuse the earlier ones
        $this->sharedExample = $this->example();
        $this->sharedHeaderComponent = $this->headerParameter();
        $this->sharedHeaderComponentName = $this->header();
        $this->sharedRequestBody = $this->requestBody();
        $this->sharedResponse = $this->response();
    }

    private function add(AbstractSchema $schema, int $count): void
    {
        $this->pool[] = $schema;
        $this->owners[] = $this->int(0, $count - 1);
    }

    /**
     * A branch of a discriminated union: the property the discriminator names has to be
     * there, and a single-value enumeration is how a document says which branch it is.
     */
    private function variant(string $kind): Schemas\Object\Schema
    {
        return new Schemas\Object\Schema(
            properties: new Schemas\Object\Properties(
                kind: new Schemas\Object\Property(
                    new Schemas\String\EnumSchema(new Schemas\String\Strings($kind)),
                    true,
                ),
                payload: new Schemas\Object\Property($this->schema(1)),
            ),
            description: $this->sentence(),
        );
    }

    private function document(): Openapi
    {
        // the shared objects are declared once, by the first document, and every other
        // document reaches for them across the file boundary
        $owner = $this->current === 0;
        $this->schemes = [];
        $this->scopes = [];
        $this->securitySchemes();
        $schemas = [];

        foreach ($this->pool as $index => $schema) {
            if (($this->owners[$index] ?? 0) === $this->current) {
                $schemas[$this->name()] = $schema;
            }
        }

        $paths = $this->paths();
        $pathItems = $this->isV31() && $this->chance(30) ? PathItems::fromArray([$this->name() => $this->path([])]) : null;
        $container = $schemas === [] ? null : Schemas\Untyped\Schemas::fromArray($schemas);

        if ($owner) {
            $this->sharedSchemas = $container;
        } elseif ($container === null && $this->sharedSchemas !== null && $this->chance(30)) {
            // the same container object: the whole section is one reference
            $container = $this->sharedSchemas;
        }

        return new Openapi(
            info: $this->info(),
            components: new Components(
                examples: $owner ? new Examples(...[$this->name() => $this->sharedExample]) : null,
                pathItems: $pathItems,
                schemas: $container,
                parameters: $owner ? $this->registeredParameters() : null,
                headers: $owner
                    ? Components\Headers::fromArray([$this->sharedHeaderComponentName => $this->sharedHeaderComponent])
                    : null,
                requestBodies: $owner ? RequestBodies::fromArray([$this->name() => $this->sharedRequestBody]) : null,
                responses: $owner ? Responses::fromArray([$this->name() => $this->sharedResponse]) : null,
                securitySchemes: $this->schemes === [] ? null : SecuritySchemes::fromArray($this->schemes),
                links: ($this->operations !== [] || $this->anonymous !== []) && $this->chance(25)
                    ? new Links(...[$this->name() => $this->link()])
                    : null,
                callbacks: $this->chance(25)
                    ? Components\Callbacks::fromArray([$this->name() => $this->callback()])
                    : null,
                extensions: $this->extensions(),
            ),
            paths: $paths,
            servers: $this->chance(50) ? $this->servers() : null,
            security: $this->securities(),
            tags: $this->chance(40) ? new Tags(new Tags\Tag(name: $this->word(), description: $this->sentence())) : null,
            externalDocs: $this->chance(25)
                ? new ExternalDocs(url: 'https://example.com/docs', description: $this->sentence())
                : null,
            version: $this->version,
            jsonSchemaDialect: $this->isV31() && $this->chance(20)
                ? 'https://json-schema.org/draft/2020-12/schema'
                : null,
            webhooks: $this->isV31() && $this->chance(25)
                ? PathItems::fromArray([$this->name() => $this->path([])])
                : null,
            extensions: $this->extensions(),
        );
    }

    private function info(): Info
    {
        return new Info(
            title: 'Random ' . $this->word(),
            version: sprintf('%d.%d.%d', $this->int(0, 3), $this->int(0, 9), $this->int(0, 9)),
            summary: $this->isV31() && $this->chance(30) ? $this->sentence() : null,
            description: $this->chance(40) ? $this->sentence() : null,
            termsOfService: $this->chance(20) ? 'https://example.com/terms' : null,
            contact: $this->chance(30) ? new Info\Contact(name: $this->word(), email: 'api@example.com') : null,
            license: $this->chance(30)
                ? new Info\License(name: 'MIT', identifier: $this->isV31() && $this->chance(50) ? 'MIT' : null)
                : null,
            extensions: $this->extensions(),
        );
    }

    private function paths(): Paths
    {
        $items = [];

        for ($i = $this->int(1, 4); $i > 0; --$i) {
            $template = '/' . $this->name();
            $variables = [];

            // one template in four uses the name a registered path parameter carries, so
            // that parameter can be passed without a name and written as a `$ref`. Only in
            // the document that declares it: the template is checked against the names of
            // its own components, which is all a document knows at construction time.
            $registered = $this->current === 0 && $this->chance(25);

            for ($v = $this->int(0, 2); $v > 0; --$v) {
                $variable = $registered && $v === 1 ? $this->sharedPathName : $this->name();
                $registered = $registered && $v !== 1;
                $variables[] = $variable;
                $template .= '/{' . $variable . '}';
            }

            $items[$template] = $this->path($variables);
        }

        return Paths::fromArray($items, $this->extensions());
    }

    /**
     * @param list<string> $variables the template's placeholders: every one of them needs
     *                                a path parameter, and there may be no others
     */
    private function path(array $variables): Paths\Path
    {
        $methods = ['get', 'post', 'put', 'delete', 'patch'];
        $chosen = [];

        for ($i = $this->int(1, 3); $i > 0; --$i) {
            $chosen[$this->pick($methods)] = true;
        }

        // the path-level parameters cover the template for every operation at once, or
        // each operation declares them itself
        $shared = $this->chance(50);
        $operations = [];

        foreach (array_keys($chosen) as $method) {
            $operations[$method] = $this->operation($shared ? [] : $variables, $method);
        }

        return new Paths\Path(
            get: $operations['get'] ?? null,
            put: $operations['put'] ?? null,
            post: $operations['post'] ?? null,
            delete: $operations['delete'] ?? null,
            patch: $operations['patch'] ?? null,
            parameters: $shared ? $this->parameters($variables, always: true) : null,
            summary: $this->chance(30) ? $this->sentence() : null,
            description: $this->chance(30) ? $this->sentence() : null,
            extensions: $this->extensions(),
        );
    }

    /**
     * @param list<string> $variables
     */
    private function operation(array $variables, string $method): Operation
    {
        $id = $this->chance(70) ? $method . ucfirst($this->name()) : null;

        $operation = new Operation(
            responses: $this->isV31() && $this->chance(10) ? null : $this->responses(),
            summary: $this->chance(40) ? $this->sentence() : null,
            description: $this->chance(30) ? $this->sentence() : null,
            id: $id,
            deprecated: $this->chance(15),
            parameters: $this->parameters($variables, always: $variables !== []),
            requestBody: match (true) {
                // a body declared in components is written as a `$ref` here, and in 3.1
                // a Reference Object may override its description
                $this->chance(15) => $this->sharedRequestBody,
                $this->isV31() && $this->chance(10) => new Reference(
                    $this->sharedRequestBody,
                    description: $this->sentence(),
                ),
                $this->chance(25) => $this->requestBody(),
                default => null,
            },
            security: $this->securities(),
            servers: $this->chance(15) ? $this->servers() : null,
            callbacks: $this->chance(15) ? Components\Callbacks::fromArray([$this->name() => $this->callback()]) : null,
            externalDocs: $this->chance(10)
                ? new ExternalDocs(url: 'https://example.com/' . $this->word(), description: null)
                : null,
            extensions: $this->extensions(),
        );

        if ($id === null) {
            $this->anonymous[] = $operation;
        } else {
            $this->operations[$id] = $operation;
        }

        return $operation;
    }

    private function responses(): Responses
    {
        $codes = ['200', '201', '204', '400', '404', '4XX', '5XX', 'default'];
        $items = [];

        for ($i = $this->int(1, 3); $i > 0; --$i) {
            $code = $this->pick($codes);

            $items[$code] = match (true) {
                // the same object in two places is what turns into a `$ref`
                $this->chance(20) => $this->sharedResponse,
                $this->isV31() && $this->chance(15) => new Reference(
                    $this->sharedResponse,
                    description: $this->sentence(),
                ),
                default => $this->response(),
            };
        }

        return Responses::fromArray($items, $this->extensions());
    }

    private function response(): Responses\Response
    {
        return new Responses\Response(
            description: $this->sentence(),
            headers: $this->chance(25)
                ? Components\Headers::fromArray($this->chance(50)
                    ? [$this->sharedHeaderComponentName => $this->sharedHeaderComponent]
                    : [$this->header() => $this->headerParameter()])
                : null,
            content: $this->chance(70) ? $this->contents() : null,
            links: ($this->operations !== [] || $this->anonymous !== []) && $this->chance(20)
                ? new Links(...[$this->name() => $this->link()])
                : null,
            extensions: $this->extensions(),
        );
    }

    private function contents(): RequestBodies\Contents
    {
        $items = [];

        for ($i = $this->int(1, 2); $i > 0; --$i) {
            $mimeType = $this->pick(self::MIME_TYPES);
            $schema = $this->schema(2);

            // one value or a map of them: the specification makes example and examples
            // mutually exclusive
            $illustration = $this->int(0, 2);

            $items[$mimeType] = new RequestBodies\Content(
                schema: $this->chance(90) ? $schema : null,
                example: $illustration === 1 ? new Schemas\Untyped\Value($this->word()) : null,
                examples: $illustration === 2
                    ? new Examples(...[$this->name() => $this->chance(50)
                        ? $this->sharedExample
                        : $this->example()])
                    : null,
                // an Encoding Object applies to forms and multipart only
                encoding: $mimeType === 'multipart/form-data' && $this->chance(40)
                    ? RequestBodies\Encodings::fromArray([$this->word() => new RequestBodies\Encoding(
                        contentType: 'application/octet-stream',
                    )])
                    : null,
                extensions: $this->extensions(),
            );
        }

        return RequestBodies\Contents::fromArray($items);
    }

    private function requestBody(): RequestBodies\RequestBody
    {
        return new RequestBodies\RequestBody(
            content: $this->contents(),
            required: $this->chance(50),
            description: $this->chance(40) ? $this->sentence() : null,
            extensions: $this->extensions(),
        );
    }

    /**
     * @param list<string> $variables
     */
    private function parameters(array $variables, bool $always): ?Parameters\Parameters
    {
        if ($variables === [] && !$always && !$this->chance(50)) {
            return null;
        }

        $paths = [];

        $registered = [];

        foreach ($variables as $variable) {
            if ($variable === $this->sharedPathName) {
                // the name comes from the registration, so the item goes in positionally —
                // and PHP wants the positional arguments first when the array is unpacked
                $registered[] = $this->sharedPathParameter;

                continue;
            }

            $paths[$variable] = $this->chance(15)
                ? $this->contentParameter()
                : new Parameters\Path\SchemaParameter(
                    schema: $this->schema(1),
                    style: $this->chance(20) ? Parameters\Path\Style::Label : Parameters\Path\Style::Simple,
                    description: $this->chance(30) ? $this->sentence() : null,
                );
        }

        $queries = [];

        // a parameter registered in components takes its name from the registration and
        // is written as a `$ref`: the only place where a map item may have no name, so it
        // goes in positionally
        if ($this->chance(30)) {
            $queries[] = $this->sharedQuery;
        }

        for ($i = $this->int(0, 2); $i > 0; --$i) {
            $queries[$this->name()] = $this->chance(20)
                ? $this->contentParameter()
                : new Parameters\Query\SchemaParameter(
                    schema: $this->schema(1),
                    explode: $this->chance(30) ? $this->chance(50) : null,
                    allowEmptyValue: $this->chance(15),
                    allowReserved: $this->chance(15),
                    required: $this->chance(30),
                    deprecated: $this->chance(10),
                    style: $this->chance(25) ? Parameters\Query\Style::PipeDelimited : Parameters\Query\Style::Form,
                );
        }

        return new Parameters\Parameters(
            // unpacking rather than fromArray(): an integer key has to stay a positional
            // argument, and fromArray() would read it as the name "0"
            queries: $queries === [] ? null : new Parameters\Query\Queries(...$queries),
            // one roll rather than several chances: two identical conditions in a match
            // are one condition to a static analyser, and the second arm is dead
            headers: match ($this->int(0, 9)) {
                0, 1 => new Parameters\Header\Headers($this->sharedHeaderParameter),
                2 => Parameters\Header\Headers::fromArray([$this->header() => $this->contentParameter()]),
                3, 4 => Parameters\Header\Headers::fromArray([$this->header() => $this->headerParameter()]),
                default => null,
            },
            cookies: match ($this->int(0, 9)) {
                0 => new Parameters\Cookie\Cookies($this->sharedCookieParameter),
                1 => Parameters\Cookie\Cookies::fromArray([$this->name() => $this->contentParameter()]),
                2, 3 => Parameters\Cookie\Cookies::fromArray([
                    $this->name() => new Parameters\Cookie\SchemaParameter(schema: $this->schema(1)),
                ]),
                default => null,
            },
            // unpacking rather than fromArray(): a registered parameter goes in without a
            // name, and fromArray() would read its integer key as the name "0"
            paths: $paths === [] && $registered === []
                ? null
                : new Parameters\Path\Paths(...[...$registered, ...$paths]),
        );
    }

    /**
     * The parameters components declares: one of every location, so a `$ref` to each is
     * written and read back.
     */
    private function registeredParameters(): Parameters
    {
        return Parameters::fromArray([
            $this->sharedParameter->name => $this->sharedParameter,
            $this->sharedHeaderParameterName => new Parameters\Parameter(
                name: $this->sharedHeaderParameterName,
                parameter: $this->sharedHeaderParameter,
            ),
            $this->sharedCookieParameterName => new Parameters\Parameter(
                name: $this->sharedCookieParameterName,
                parameter: $this->sharedCookieParameter,
            ),
            $this->sharedPathName => new Parameters\Parameter(
                name: $this->sharedPathName,
                parameter: $this->sharedPathParameter,
            ),
        ]);
    }

    /**
     * A parameter whose value is a media type rather than a schema. Its location comes
     * from where it is used, so the same class serves every one of them.
     */
    private function contentParameter(): Parameters\ContentParameter
    {
        return new Parameters\ContentParameter(
            mimeType: $this->chance(50) ? 'application/json' : 'text/plain',
            content: new RequestBodies\Content(schema: $this->schema(1)),
            required: $this->chance(30),
        );
    }

    private function headerParameter(): Parameters\Header\SchemaParameter
    {
        return new Parameters\Header\SchemaParameter(
            schema: $this->schema(1),
            required: $this->chance(25),
            deprecated: $this->chance(10),
        );
    }

    private function callback(): PathItems
    {
        // an operation inside a callback is an operation of the document as well: it may
        // carry an operationId, and a Link may name it
        $id = $this->chance(40) ? 'callback' . $this->counter++ : null;
        $operation = new Operation(
            responses: new Responses(x204: new Responses\Response(description: 'Accepted')),
            id: $id,
        );

        if ($id === null) {
            $this->anonymous[] = $operation;
        } else {
            $this->operations[$id] = $operation;
        }

        return PathItems::fromArray(
            ['{$request.body#/' . $this->word() . '}' => new Paths\Path(post: $operation)],
            $this->extensions(),
        );
    }

    /**
     * A Link points at an operation by the object. The target may be an operation that is
     * built later — pagination points at itself — so the reference is deferred.
     */
    private function link(): Link
    {
        if ($this->anonymous !== [] && ($this->operations === [] || $this->chance(40))) {
            $index = $this->int(0, count($this->anonymous) - 1);
            $target = new DeferredOperation(fn (): Operation => $this->anonymous[$index] ?? $this->anonymous[0]);
        } else {
            $ids = array_keys($this->operations);
            $id = $ids === [] ? throw new LogicException('An operation went missing.') : $this->pick($ids);
            $target = new DeferredOperation(
                fn (): Operation => $this->operations[$id] ?? throw new LogicException('An operation went missing.'),
            );
        }

        return new Link(
            operation: $target,
            parameters: $this->chance(50) ? $this->linkParameters() : null,
            description: $this->chance(40) ? $this->sentence() : null,
            server: $this->chance(20)
                ? new Servers\Server(
                    url: 'https://example.com/{' . ($variable = $this->word()) . '}',
                    variables: Servers\Variables::fromArray([$variable => new Servers\Variable(
                        default: 'v1',
                        enum: $this->chance(50) ? new Schemas\String\Strings('v1', 'v2') : null,
                        description: $this->chance(50) ? $this->sentence() : null,
                    )]),
                )
                : null,
            extensions: $this->extensions(),
        );
    }

    /**
     * Every form a Link parameter can take: the named constructors cover the usual
     * expressions, and `expression()` takes whatever else the specification allows.
     */
    private function linkParameters(): Link\Parameters
    {
        $items = [];

        for ($i = $this->int(1, 3); $i > 0; --$i) {
            $items[$this->name()] = match ($this->int(0, 8)) {
                0 => Link\Parameter::requestPath($this->word()),
                1 => Link\Parameter::requestQuery($this->word()),
                2 => Link\Parameter::requestHeader($this->header()),
                3 => Link\Parameter::requestBody($this->chance(50) ? '/' . $this->word() : null),
                4 => Link\Parameter::responseHeader($this->header()),
                5 => Link\Parameter::responseBody($this->chance(50) ? '/' . $this->word() : null),
                6 => Link\Parameter::constant((string) $this->int(1, 100)),
                7 => Link\Parameter::json($this->chance(50)
                    ? [$this->word() => $this->int(0, 10)]
                    : $this->int(0, 100)),
                default => Link\Parameter::expression('$request.path.' . $this->word()),
            };
        }

        return Link\Parameters::fromArray($items);
    }

    private function example(): Example
    {
        return new Example(
            value: new Schemas\Untyped\Value($this->chance(40)
                ? Schemas\Object\OpenapiObject::fromArray([$this->word() => $this->word()])
                : $this->word()),
            summary: $this->chance(50) ? $this->sentence() : null,
            description: $this->chance(30) ? $this->sentence() : null,
            extensions: $this->extensions(),
        );
    }

    private function servers(): Servers
    {
        $variables = [];

        for ($i = $this->int(0, 1); $i > 0; --$i) {
            $variables[$this->word()] = new Servers\Variable(
                default: 'eu',
                enum: $this->chance(50) ? new Schemas\String\Strings('eu', 'us') : null,
                description: $this->chance(30) ? $this->sentence() : null,
            );
        }

        $url = 'https://example.com/' . implode('/', array_map(
            static fn (string $name): string => '{' . $name . '}',
            array_keys($variables),
        ));

        return new Servers(new Servers\Server(
            url: rtrim($url, '/'),
            description: $this->chance(40) ? $this->sentence() : null,
            variables: $variables === [] ? null : Servers\Variables::fromArray($variables),
            extensions: $this->extensions(),
        ));
    }

    private function securitySchemes(): void
    {
        for ($i = $this->int(0, 3); $i > 0; --$i) {
            switch ($this->int(0, 5)) {
                case 0:
                    $scope = new Oauth2Security\Flows\Scope($this->sentence());
                    $this->scopes[] = $scope;
                    $scopes = Oauth2Security\Flows\Scopes::fromArray(['read:' . $this->word() => $scope]);
                    $this->schemes[$this->name()] = new Oauth2Security\Scheme(
                        flows: match ($this->int(0, 3)) {
                            0 => Oauth2Security\Flows::createAuthorizationCode(
                                new Oauth2Security\Flows\AuthorizationCodeFlow(
                                    authorizationUrl: 'https://example.com/oauth/authorize',
                                    tokenUrl: 'https://example.com/oauth/token',
                                    scopes: $scopes,
                                ),
                            ),
                            1 => Oauth2Security\Flows::createClientCredentials(
                                new Oauth2Security\Flows\ClientCredentialsFlow(
                                    tokenUrl: 'https://example.com/oauth/token',
                                    scopes: $scopes,
                                ),
                                null,
                            ),
                            2 => Oauth2Security\Flows::createPassword(
                                new Oauth2Security\Flows\PasswordFlow(
                                    tokenUrl: 'https://example.com/oauth/token',
                                    scopes: $scopes,
                                    refreshUrl: 'https://example.com/oauth/refresh',
                                ),
                                null,
                                null,
                            ),
                            default => Oauth2Security\Flows::createImplicit(
                                new Oauth2Security\Flows\ImplicitFlow(
                                    authorizationUrl: 'https://example.com/oauth/authorize',
                                    scopes: $scopes,
                                ),
                                null,
                                null,
                                null,
                            ),
                        },
                        description: $this->chance(40) ? $this->sentence() : null,
                    );

                    break;

                case 1:
                    $this->schemes[$this->name()] = new SecuritySchemes\ApiKeySecurity\Scheme(
                        name: 'X-' . ucfirst($this->word()),
                        in: SecuritySchemes\ApiKeySecurity\In::Header,
                    );

                    break;

                case 2:
                    $this->schemes[$this->name()] = new SecuritySchemes\BearerHttpSecurityScheme(format: 'JWT');

                    break;

                case 3:
                    $this->schemes[$this->name()] = new SecuritySchemes\BasicHttpSecurityScheme();

                    break;

                case 4:
                    $this->schemes[$this->name()] = new SecuritySchemes\OpenIdConnectSecurityScheme(
                        openIdConnectUrl: 'https://example.com/.well-known/openid-configuration',
                    );

                    break;

                default:
                    $this->schemes[$this->name()] = new SecuritySchemes\HttpSecurityScheme('Digest');
            }
        }
    }

    private function securities(): ?Securities
    {
        if ($this->schemes === [] || !$this->chance(35)) {
            return null;
        }

        $requirements = [];

        for ($i = $this->int(1, 2); $i > 0; --$i) {
            // an empty requirement means something of its own: anonymous access
            if ($this->chance(15)) {
                $requirements[] = new Securities\SecuritySchemes();

                continue;
            }

            $scheme = $this->pick(array_values($this->schemes));

            if ($scheme instanceof Oauth2Security\Scheme && $this->scopes !== [] && $this->chance(70)) {
                $requirements[] = new Securities\SecuritySchemes($this->pick($this->scopes));

                continue;
            }

            if ($scheme instanceof SecuritySchemes\OpenIdConnectSecurityScheme && $this->chance(50)) {
                $requirements[] = new Securities\SecuritySchemes(new Securities\ScopeName($scheme, 'openid'));

                continue;
            }

            // roles beside a scheme without scopes appeared in 3.1 only
            if (
                $this->isV31()
                && $this->chance(30)
                && (
                    $scheme instanceof SecuritySchemes\ApiKeySecurity\Scheme
                    || $scheme instanceof SecuritySchemes\BasicHttpSecurityScheme
                    || $scheme instanceof SecuritySchemes\BearerHttpSecurityScheme
                    || $scheme instanceof SecuritySchemes\HttpSecurityScheme
                )
            ) {
                // a role names a scheme that has no scopes of its own, so the type of the
                // parameter admits exactly those schemes
                $requirements[] = new Securities\SecuritySchemes(new Securities\Role($scheme, 'admin'));

                continue;
            }

            if ($scheme instanceof Oauth2Security\Scheme) {
                $requirements[] = new Securities\SecuritySchemes(new Securities\ScopeName($scheme, 'read:all'));

                continue;
            }

            $requirements[] = new Securities\SecuritySchemes($scheme);
        }

        return new Securities(...$requirements);
    }

    private function schema(int $depth): AbstractSchema
    {
        // reusing a schema that components declare is what makes a `$ref` appear
        if ($this->pool !== [] && $this->chance(20)) {
            return $this->pick($this->pool);
        }

        $kinds = [
            'string', 'integer', 'number', 'boolean', 'untyped',
            'enum-string', 'enum-integer', 'enum-number', 'enum-boolean', 'enum-mixed',
        ];

        if ($depth > 0) {
            $kinds[] = 'object';
            $kinds[] = 'array';
            $kinds[] = 'composition';
            $kinds[] = 'enum-object';
            $kinds[] = 'enum-array';
        }

        if ($this->isV31()) {
            $kinds[] = 'null';
        }

        return match ($this->pick($kinds)) {
            'string' => $this->stringSchema(),
            'integer' => $this->numericSchema(true),
            'number' => $this->numericSchema(false),
            'boolean' => new Schemas\Boolean\Schema(
                description: $this->chance(30) ? $this->sentence() : null,
                nullable: $this->chance(20),
                default: $this->chance(30) ? new Schemas\Boolean\Value($this->chance(50)) : null,
                example: $this->chance(20) ? new Schemas\Boolean\Value($this->chance(50)) : null,
            ),
            'null' => new Schemas\Null\Schema(description: $this->chance(50) ? $this->sentence() : null),
            'untyped' => new Schemas\Untyped\Schema(
                description: $this->chance(40) ? $this->sentence() : null,
                format: $this->chance(20) ? $this->word() . '-map' : null,
                // a null value is a value: the example of an empty response
                example: $this->chance(20) ? new Schemas\Untyped\Value($this->chance(50) ? null : $this->word()) : null,
                extensions: $this->extensions(),
            ),
            'enum-string' => new Schemas\String\EnumSchema(
                new Schemas\String\Strings(...$this->words($this->int(1, 3))),
                description: $this->chance(30) ? $this->sentence() : null,
                nullable: $this->chance(20),
                format: $this->chance(20) ? $this->word() . '-kind' : null,
                contentEncoding: $this->isV31() && $this->chance(20) ? 'base64' : null,
                contentMediaType: $this->isV31() && $this->chance(20) ? 'application/json' : null,
            ),
            'enum-integer' => new Schemas\Integer\EnumSchema(
                new Schemas\Integer\Integers(...$this->numbers($this->int(1, 3))),
                nullable: $this->chance(20),
            ),
            'enum-number' => new Schemas\Number\EnumSchema(
                new Schemas\Number\Numbers(...array_map(
                    static fn (int $value): float => $value + 0.5,
                    $this->numbers($this->int(1, 3)),
                )),
                xml: $this->chance(20) ? $this->xml() : null,
            ),
            'enum-boolean' => new Schemas\Boolean\EnumSchema(
                $this->chance(50),
                description: $this->chance(40) ? $this->sentence() : null,
            ),
            'enum-object' => new Schemas\Object\EnumSchema(
                new Schemas\Object\Objects(Schemas\Object\OpenapiObject::fromArray([
                    $this->word() => $this->word(),
                    $this->name() => $this->int(0, 10),
                ])),
                nullable: $this->chance(20),
            ),
            'enum-array' => new Schemas\Array\EnumSchema(
                new Schemas\Array\Arrays(new Schemas\Array\OpenapiArray(
                    $this->word(),
                    $this->int(0, 10),
                    Schemas\Object\OpenapiObject::fromArray([$this->word() => null]),
                )),
            ),
            'enum-mixed' => new Schemas\Untyped\EnumSchema(
                new Schemas\Untyped\Values($this->word(), $this->int(0, 100)),
                nullable: $this->chance(20),
            ),
            'object' => $this->objectSchema($depth),
            'array' => $this->arraySchema($depth),
            default => $this->composition($depth),
        };
    }

    private function stringSchema(): Schemas\String\Schema
    {
        $minLength = $this->chance(40) ? max(0, $this->int(0, 5)) : 0;

        return new Schemas\String\Schema(
            description: $this->chance(30) ? $this->sentence() : null,
            nullable: $this->chance(20),
            access: $this->chance(15) ? Schemas\Abstract\Access::ReadOnly : null,
            deprecated: $this->chance(10),
            default: $this->chance(25) ? new Schemas\String\Value($this->word()) : null,
            example: $this->chance(20) ? new Schemas\String\Value($this->word()) : null,
            minLength: $minLength,
            maxLength: $this->chance(40) ? max(0, $minLength + $this->int(0, 20)) : null,
            pattern: $this->chance(25) ? '^[a-z]+$' : null,
            format: $this->chance(30) ? Schemas\String\Format::Uuid : null,
            contentEncoding: $this->isV31() && $this->chance(15) ? 'base64' : null,
            contentMediaType: $this->isV31() && $this->chance(15) ? 'application/json' : null,
            // a schema may carry a check without declaring a type
            declareType: !$this->chance(15),
            extensions: $this->extensions(),
            xml: $this->chance(15) ? $this->xml() : null,
        );
    }

    private function xml(): Schemas\Abstract\Xml
    {
        return new Schemas\Abstract\Xml(
            name: $this->chance(70) ? $this->word() : null,
            namespace: $this->chance(30) ? 'https://example.com/' . $this->word() : null,
            prefix: $this->chance(30) ? substr($this->word(), 0, 2) : null,
            attribute: $this->chance(30),
            wrapped: $this->chance(30),
            extensions: $this->extensions(),
        );
    }

    private function numericSchema(bool $integer): Schemas\Integer\Schema|Schemas\Number\Schema
    {
        $minimum = $this->chance(50) ? $this->int(-100, 100) : null;
        $maximum = $this->chance(50) ? ($minimum ?? 0) + $this->int(0, 1000) : null;
        $exclusiveMinimum = $minimum !== null && $this->chance(25);
        $exclusiveMaximum = $maximum !== null && $this->chance(25);
        $multipleOf = $this->chance(25) ? $this->int(1, 10) : null;
        $description = $this->chance(30) ? $this->sentence() : null;
        $declareType = !$this->chance(15);

        return $integer
            ? new Schemas\Integer\Schema(
                description: $description,
                nullable: $this->chance(20),
                default: $this->chance(20) ? new Schemas\Integer\Value($minimum ?? 0) : null,
                minimum: $minimum,
                maximum: $maximum,
                exclusiveMinimum: $exclusiveMinimum,
                exclusiveMaximum: $exclusiveMaximum,
                multipleOf: $multipleOf,
                format: $this->chance(25) ? Schemas\Integer\Format::Int64 : null,
                declareType: $declareType,
                example: $this->chance(20) ? new Schemas\Integer\Value($minimum ?? 1) : null,
            )
            : new Schemas\Number\Schema(
                description: $description,
                nullable: $this->chance(20),
                default: $this->chance(20) ? new Schemas\Number\Value(($minimum ?? 0) + 0.5) : null,
                minimum: $minimum,
                maximum: $maximum,
                exclusiveMinimum: $exclusiveMinimum,
                exclusiveMaximum: $exclusiveMaximum,
                multipleOf: $multipleOf,
                format: $this->chance(25) ? Schemas\Number\Format::Double : null,
                declareType: $declareType,
                example: $this->chance(20) ? new Schemas\Number\Value(($minimum ?? 1) + 0.25) : null,
            );
    }

    private function objectSchema(int $depth): Schemas\Object\Schema
    {
        $properties = [];

        for ($i = $this->int(1, 3); $i > 0; --$i) {
            $properties[$this->word() . $this->counter++] = new Schemas\Object\Property(
                $this->schema($depth - 1),
                $this->chance(40),
            );
        }

        // `required` names that properties does not describe need somewhere for their
        // shape to come from, so they are only written while additionalProperties stands
        $additionalProperties = $this->chance(25) ? false : true;
        $condition = $this->isV31() && $depth > 0 && $this->chance(20)
            ? [
                new Schemas\Object\Schema(declareType: false, minProperties: 1),
                $this->schema($depth - 1),
                $this->chance(50) ? $this->schema($depth - 1) : null,
            ]
            : null;

        return new Schemas\Object\Schema(
            properties: Schemas\Object\Properties::fromArray($properties),
            description: $this->chance(30) ? $this->sentence() : null,
            nullable: $this->chance(15),
            patternProperties: $this->isV31() && $this->chance(20)
                ? Schemas\Object\PatternProperties::fromArray(['^x-' => $this->schema($depth - 1)])
                : null,
            propertyNames: $this->isV31() && $this->chance(15)
                ? new Schemas\String\Schema(pattern: '^[a-z-]+$')
                : null,
            dependentRequired: $this->isV31() && $this->chance(15)
                ? Schemas\Object\DependentRequired::fromArray([
                    array_key_first($properties) => new Schemas\String\Strings($this->word()),
                ])
                : null,
            unevaluatedProperties: $this->isV31() && $this->chance(15) ? false : null,
            minProperties: $this->chance(20) ? max(0, $this->int(0, 2)) : 0,
            maxProperties: $this->chance(20) ? max(0, $this->int(3, 20)) : null,
            additionalProperties: $additionalProperties,
            // an empty map and a null are values of their own, and both have to survive
            example: $this->chance(20)
                ? new Schemas\Object\Value(match ($this->int(0, 2)) {
                    0 => null,
                    1 => new Schemas\Object\OpenapiObject(),
                    default => Schemas\Object\OpenapiObject::fromArray([$this->word() => $this->int(0, 9)]),
                })
                : null,
            declareType: !$this->chance(15),
            required: $additionalProperties === true && $this->chance(20)
                ? new Schemas\String\Strings('x-' . $this->word())
                : null,
            extensions: $this->extensions(),
            xml: $this->chance(10) ? $this->xml() : null,
            // a condition is written as a whole: `then` without `if` is rejected
            if: $condition !== null ? $condition[0] : null,
            then: $condition !== null ? $condition[1] : null,
            else: $condition !== null ? $condition[2] : null,
        );
    }

    private function arraySchema(int $depth): Schemas\Array\Schema
    {
        $minItems = $this->chance(30) ? max(0, $this->int(0, 3)) : 0;
        $declareType = !$this->chance(15);

        return new Schemas\Array\Schema(
            // 3.0 requires items on a declared array type, and there is no reason to
            // leave it out otherwise
            items: $declareType || $this->chance(70) ? $this->schema($depth - 1) : null,
            description: $this->chance(30) ? $this->sentence() : null,
            nullable: $this->chance(15),
            minItems: $minItems,
            maxItems: $this->chance(30) ? max(0, $minItems + $this->int(0, 10)) : null,
            uniqueItems: $this->chance(25),
            example: $this->chance(20)
                ? new Schemas\Array\Value(match ($this->int(0, 2)) {
                    0 => null,
                    1 => new Schemas\Array\OpenapiArray(),
                    default => new Schemas\Array\OpenapiArray($this->word(), $this->int(0, 9)),
                })
                : null,
            prefixItems: $this->isV31() && $this->chance(20)
                ? new Schemas\Untyped\Schemas($this->schema($depth - 1))
                : null,
            contains: $this->isV31() && $this->chance(20) ? $this->schema($depth - 1) : null,
            unevaluatedItems: $this->isV31() && $this->chance(15) ? false : null,
            declareType: $declareType,
            extensions: $this->extensions(),
        );
    }

    private function composition(int $depth): Schemas\Untyped\Schema
    {
        $branches = [];

        for ($i = $this->int(2, 3); $i > 0; --$i) {
            $branches[] = $this->schema($depth - 1);
        }

        return match ($this->int(0, 3)) {
            0 => new Schemas\Untyped\Schema(allOf: new Schemas\Untyped\Schemas(...$branches)),
            1 => new Schemas\Untyped\Schema(anyOf: new Schemas\Untyped\Schemas(...$branches)),
            2 => new Schemas\Untyped\Schema(oneOf: new Schemas\Untyped\Schemas(...$branches)),
            default => new Schemas\Untyped\Schema(
                not: $this->schema($depth - 1),
                description: $this->chance(40) ? $this->sentence() : null,
            ),
        };
    }

    private function extensions(): ?Extensions
    {
        if (!$this->chance(25)) {
            return null;
        }

        $items = [];

        for ($i = $this->int(1, 2); $i > 0; --$i) {
            $items[$this->word() . '-' . $this->counter++] = match ($this->int(0, 3)) {
                0 => $this->word(),
                1 => $this->int(0, 1000),
                2 => $this->chance(50),
                default => Schemas\Object\OpenapiObject::fromArray([$this->word() => $this->int(0, 10)]),
            };
        }

        return Extensions::fromArray($items);
    }

    private function isV31(): bool
    {
        return $this->version->isV31();
    }

    /**
     * @template TItem
     *
     * @param non-empty-list<TItem> $values
     *
     * @return TItem
     */
    private function pick(array $values): mixed
    {
        return $values[$this->int(0, count($values) - 1)] ?? $values[0];
    }

    private function word(): string
    {
        return $this->pick(self::WORDS);
    }

    /**
     * @return list<string>
     */
    private function words(int $count): array
    {
        $result = [];

        for ($i = 0; $i < $count; ++$i) {
            // an enumeration rejects repeats, so the values are made distinct
            $result[] = $this->word() . '-' . $this->counter++;
        }

        return $result;
    }

    /**
     * @return list<int>
     */
    private function numbers(int $count): array
    {
        $result = [];

        for ($i = 0; $i < $count; ++$i) {
            $result[] = $this->counter++ * 10 + $this->int(0, 9);
        }

        return $result;
    }

    /**
     * A name unique within the document: the maps reject repeats, and a collision would
     * look like a defect of the package.
     */
    private function name(): string
    {
        return $this->word() . $this->counter++;
    }

    private function header(): string
    {
        return 'X-' . ucfirst($this->word()) . '-' . $this->counter++;
    }

    private function sentence(): string
    {
        return ucfirst($this->word()) . ' ' . $this->word() . '.';
    }

    private function chance(int $percent): bool
    {
        return $this->int(1, 100) <= $percent;
    }

    private function int(int $min, int $max): int
    {
        return $min + $this->next() % ($max - $min + 1);
    }

    private function next(): int
    {
        // xorshift32: a handful of operations, and the same sequence on every runtime
        $x = $this->state;
        $x ^= ($x << 13) & 0xFFFFFFFF;
        $x ^= $x >> 17;
        $x ^= ($x << 5) & 0xFFFFFFFF;

        return $this->state = $x & 0xFFFFFFFF;
    }
}
