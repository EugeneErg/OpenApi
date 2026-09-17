<?php

declare(strict_types = 1);

use EugeneErg\OpenApi\Components;
use EugeneErg\OpenApi\Components\Examples;
use EugeneErg\OpenApi\Components\Examples\Example;
use EugeneErg\OpenApi\Components\Links;
use EugeneErg\OpenApi\Components\Links\Link;
use EugeneErg\OpenApi\Components\Parameters;
use EugeneErg\OpenApi\Components\RequestBodies;
use EugeneErg\OpenApi\Components\Responses;
use EugeneErg\OpenApi\Components\Schemas;
use EugeneErg\OpenApi\Components\SecuritySchemes;
use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security;
use EugeneErg\OpenApi\Extensions;
use EugeneErg\OpenApi\ExternalDocs;
use EugeneErg\OpenApi\Info;
use EugeneErg\OpenApi\Openapi;
use EugeneErg\OpenApi\PathItems;
use EugeneErg\OpenApi\Paths;
use EugeneErg\OpenApi\Paths\DeferredOperation;
use EugeneErg\OpenApi\Securities;
use EugeneErg\OpenApi\Servers;
use EugeneErg\OpenApi\Tags;
use EugeneErg\OpenApi\Version;

/**
 * The same as kitchen-sink-31 but in 3.0: without the JSON Schema 2020-12 vocabulary,
 * the null type, roles, webhooks, components.pathItems and the Reference Object's own
 * fields. Validity is checked by an external validator (`composer validate-output`).
 *
 * The body is wrapped in a closure: require runs the file in the caller's scope, and the
 * deferred references are bound through use (&$var).
 *
 * @return array<string, Openapi>
 */
return (static function (): array {
    $street = new Schemas\String\Schema(minLength: 1, maxLength: 80, pattern: '^[^\n]+$');

    // the 2020-12 vocabulary: $defs, $anchor, $dynamicAnchor and a reference to it
    $node = new Schemas\Object\Schema(
        properties: new Schemas\Object\Properties(
            value: new Schemas\Object\Property(schema: new Schemas\String\Schema(), required: true),
        ),
        extensions: new Extensions(internal: true),
    );

    $tree = new Schemas\Object\Schema(
        properties: new Schemas\Object\Properties(
            kind: new Schemas\Object\Property(
                schema: new Schemas\String\EnumSchema(
                    new Schemas\String\Strings('binary', 'trie'),
                    format: 'tree-kind',
                ),
            ),
        ),
        minProperties: 1,
        maxProperties: 20,
        additionalProperties: new Schemas\Untyped\Schema(),
    );

    $measurement = new Schemas\Number\Schema(
        minimum: 0,
        maximum: 9223372036854775807,
        exclusiveMinimum: true,
        multipleOf: 0.5,
        format: Schemas\Number\Format::Double,
        xml: new Schemas\Abstract\Xml(name: 'measure', attribute: true),
    );

    $tuple = new Schemas\Array\Schema(
        items: new Schemas\Untyped\Schema(),
        minItems: 2,
        maxItems: 5,
        uniqueItems: true,
    );

    $attachment = new Schemas\String\Schema(format: Schemas\String\Format::Binary);

    // if / then / else and a composition with a discriminator
    $card = new Schemas\Object\Schema(
        properties: new Schemas\Object\Properties(
            kind: new Schemas\Object\Property(
                schema: new Schemas\String\EnumSchema(new Schemas\String\Strings('credit')),
                required: true,
            ),
        ),
        access: Schemas\Abstract\Access::ReadOnly,
        externalDocs: new ExternalDocs(url: 'https://example.com/cards', description: 'Cards'),
    );

    $cash = new Schemas\Object\Schema(
        properties: new Schemas\Object\Properties(
            kind: new Schemas\Object\Property(
                schema: new Schemas\String\EnumSchema(new Schemas\String\Strings('cash')),
                required: true,
            ),
        ),
    );

    $payment = new Schemas\Untyped\Schema(
        oneOf: new Schemas\Untyped\Schemas($card, $cash),
        discriminator: new Schemas\Abstract\Discriminator(
            propertyName: 'kind',
            mapping: new Schemas\Untyped\Schemas(credit: $card, cash: $cash),
        ),
        deprecated: true,
    );

    $flag = new Schemas\Boolean\EnumSchema(true, description: 'Always true.');

    // security: oauth2 with every flow, scopes by object and by name, a role, mutualTLS
    $read = new Oauth2Security\Flows\Scope('Read everything');
    $write = new Oauth2Security\Flows\Scope('Write everything');
    $oauth = new Oauth2Security\Scheme(
        flows: Oauth2Security\Flows::createImplicit(
            new Oauth2Security\Flows\ImplicitFlow(
                authorizationUrl: 'https://example.com/oauth/authorize',
                scopes: Oauth2Security\Flows\Scopes::fromArray(['read:all' => $read]),
                refreshUrl: 'https://example.com/oauth/refresh',
            ),
            new Oauth2Security\Flows\PasswordFlow(
                tokenUrl: 'https://example.com/oauth/token',
                scopes: Oauth2Security\Flows\Scopes::fromArray(['write:all' => $write]),
            ),
            new Oauth2Security\Flows\ClientCredentialsFlow(
                tokenUrl: 'https://example.com/oauth/token',
                scopes: new Oauth2Security\Flows\Scopes(),
                extensions: new Extensions(machine: true),
            ),
            new Oauth2Security\Flows\AuthorizationCodeFlow(
                authorizationUrl: 'https://example.com/oauth/authorize',
                tokenUrl: 'https://example.com/oauth/token',
                scopes: new Oauth2Security\Flows\Scopes(),
            ),
        ),
        description: 'Everything OAuth2 has.',
    );
    $apiKey = new SecuritySchemes\ApiKeySecurity\Scheme(
        name: 'X-Api-Key',
        in: SecuritySchemes\ApiKeySecurity\In::Cookie,
    );
    $bearer = new SecuritySchemes\BearerHttpSecurityScheme(format: 'JWT');
    $digest = new SecuritySchemes\HttpSecurityScheme('Digest');
    $oidc = new SecuritySchemes\OpenIdConnectSecurityScheme(
        openIdConnectUrl: 'https://example.com/.well-known/openid-configuration',
    );

    $notFound = new Responses\Response(description: 'Not found');
    $shared = new Parameters\Query\SchemaParameter(schema: new Schemas\Integer\Schema(minimum: 1));
    $traceId = new Parameters\Header\SchemaParameter(schema: new Schemas\String\Schema());
    $listExample = new Example(
        value: new Schemas\Untyped\Value(new Schemas\Array\OpenapiArray(
            Schemas\Object\OpenapiObject::fromArray(['kind' => 'cash', '0' => null]),
        )),
        summary: 'One payment',
        extensions: new Extensions(source: 'docs'),
    );

    // the operation refers to itself: pagination
    $listPayments = new Paths\Operation(
        responses: Responses::fromArray(
            [
                '200' => new Responses\Response(
                    description: 'A page of payments',
                    headers: Components\Headers::fromArray([
                        'X-Rate-Limit' => $traceId,
                        'X-Trace' => new Parameters\ContentParameter(
                            mimeType: 'application/json',
                            content: new RequestBodies\Content(schema: new Schemas\String\Schema()),
                        ),
                    ]),
                    content: RequestBodies\Contents::fromArray([
                        'application/json' => new RequestBodies\Content(
                            schema: new Schemas\Array\Schema(items: $payment),
                            examples: new Examples(page: $listExample),
                        ),
                    ]),
                    links: new Links(
                        next: new Link(
                            operation: new DeferredOperation(
                                static function () use (&$listPayments): Paths\Operation {
                                    return $listPayments;
                                },
                            ),
                            parameters: new Link\Parameters(
                                page: Link\Parameter::responseBody('/next'),
                                trace: Link\Parameter::requestHeader('X-Trace'),
                                fixed: Link\Parameter::constant('1'),
                            ),
                            description: 'The next page.',
                            server: new Servers\Server(url: 'https://example.com'),
                        ),
                    ),
                    extensions: new Extensions(cache: 60),
                ),
                '4XX' => $notFound,
                'default' => $notFound,
            ],
            new Extensions(...['error-codes' => new Schemas\Array\OpenapiArray(400, 404)]),
        ),
        summary: 'List payments',
        description: 'Everything an operation can carry.',
        id: 'listPayments',
        parameters: new Parameters\Parameters(
            queries: new Parameters\Query\Queries(
                $shared,
                filter: new Parameters\Query\SchemaParameter(
                    schema: new Schemas\Object\Schema(),
                    style: Parameters\Query\Style::DeepObject,
                    explode: true,
                    allowReserved: true,
                    allowEmptyValue: true,
                ),
                tags: new Parameters\Query\SchemaParameter(
                    schema: new Schemas\Array\Schema(items: new Schemas\String\Schema()),
                    style: Parameters\Query\Style::PipeDelimited,
                ),
                body: new Parameters\ContentParameter(
                    mimeType: 'application/json',
                    content: new RequestBodies\Content(schema: new Schemas\Untyped\Schema()),
                ),
            ),
            headers: new Parameters\Header\Headers(
                x_trace: new Parameters\Header\SchemaParameter(
                    schema: new Schemas\String\Schema(format: Schemas\String\Format::Uuid),
                    deprecated: true,
                ),
            ),
            cookies: new Parameters\Cookie\Cookies(
                session: new Parameters\Cookie\SchemaParameter(schema: new Schemas\String\Schema()),
            ),
        ),
        tags: new Tags(new Tags\Tag(name: 'payments')),
        security: new Securities(
            new Securities\SecuritySchemes($read, $write, $apiKey),
            new Securities\SecuritySchemes(new Securities\ScopeName($oidc, 'openid')),
            new Securities\SecuritySchemes(),
        ),
        servers: new Servers(new Servers\Server(url: 'https://payments.example.com')),
        callbacks: Components\Callbacks::fromArray([
            'onPaid' => PathItems::fromArray(
                ['{$request.body#/callbackUrl}' => new Paths\Path(
                    post: new Paths\Operation(
                        responses: new Responses(x204: new Responses\Response(description: 'Accepted')),
                    ),
                )],
                new Extensions(retry: 3),
            ),
        ]),
        externalDocs: new ExternalDocs(url: 'https://example.com/payments', description: null),
        extensions: new Extensions(...['curl-samples' => new Schemas\Array\OpenapiArray('curl …')]),
    );

    $upload = new Paths\Operation(
        responses: new Responses(x201: new Responses\Response(description: 'Created')),
        id: 'upload',
        requestBody: new RequestBodies\RequestBody(
            content: RequestBodies\Contents::fromArray([
                'multipart/form-data' => new RequestBodies\Content(
                    schema: new Schemas\Object\Schema(
                        properties: new Schemas\Object\Properties(
                            file: new Schemas\Object\Property(schema: $attachment, required: true),
                            meta: new Schemas\Object\Property(schema: $tree),
                        ),
                    ),
                    encoding: RequestBodies\Encodings::fromArray([
                        'file' => new RequestBodies\Encoding(
                            contentType: 'application/octet-stream',
                            headers: new Components\Headers(x_size: $traceId),
                            extensions: new Extensions(streamed: true),
                        ),
                        'meta' => new RequestBodies\Encoding(
                            style: RequestBodies\EncodingStyle::DeepObject,
                            explode: true,
                            allowReserved: false,
                        ),
                    ]),
                ),
            ]),
            required: true,
            description: 'An upload',
        ),
    );

    $ping = new Paths\Path(
        get: new Paths\Operation(responses: new Responses(x200: new Responses\Response(description: 'Pong'))),
        summary: 'Health check',
        description: 'Answers while the service is alive.',
        servers: new Servers(new Servers\Server(url: 'https://health.example.com')),
        extensions: new Extensions(owner: 'platform'),
    );

    return ['kitchen-sink-30.json' => new Openapi(
        info: new Info(
            title: 'Everything the package can build',
            version: '1.0.0',
            description: 'Built by tests/Cases/BuilderTest/Objects/kitchen-sink-30.php.',
            termsOfService: 'https://example.com/terms',
            contact: new Info\Contact(
                name: 'API team',
                url: 'https://example.com/team',
                email: 'api@example.com',
                extensions: new Extensions(slack: '#api'),
            ),
            license: new Info\License(name: 'MIT', url: 'https://example.com/mit'),
            extensions: new Extensions(logo: Schemas\Object\OpenapiObject::fromArray(['url' => 'https://example.com/logo.png'])),
        ),
        components: new Components(
            examples: new Examples(page: $listExample),
            schemas: new Schemas\Untyped\Schemas(
                Street: $street,
                Node: $node,
                Tree: $tree,
                Measurement: $measurement,
                Tuple: $tuple,
                Attachment: $attachment,
                Card: $card,
                Cash: $cash,
                Payment: $payment,
                Flag: $flag,
            ),
            parameters: new Parameters(
                page: new Parameters\Parameter(name: 'page', parameter: $shared),
                trace: new Parameters\Parameter(name: 'X-Trace', parameter: $traceId),
            ),
            headers: new Components\Headers(x_trace: $traceId),
            requestBodies: new RequestBodies(
                Upload: new RequestBodies\RequestBody(
                    content: RequestBodies\Contents::fromArray([
                        'text/plain' => new RequestBodies\Content(schema: new Schemas\String\Schema()),
                    ]),
                ),
            ),
            responses: new Responses(NotFound: $notFound),
            securitySchemes: new SecuritySchemes(
                oauth: $oauth,
                apiKey: $apiKey,
                bearer: $bearer,
                digest: $digest,
                oidc: $oidc,
            ),
            links: new Links(
                self: new Link(
                    operation: new DeferredOperation(
                        static function () use (&$listPayments): Paths\Operation {
                            return $listPayments;
                        },
                    ),
                ),
            ),
            callbacks: Components\Callbacks::fromArray([
                'onPing' => new PathItems(ping: $ping),
            ]),
            extensions: new Extensions(generated: true),
        ),
        paths: Paths::fromArray(
            [
                '/payments' => new Paths\Path(get: $listPayments, post: $upload),
                '/payments/{id}' => new Paths\Path(
                    get: new Paths\Operation(
                        responses: new Responses(x200: new Responses\Response(description: 'One payment')),
                        parameters: new Parameters\Parameters(
                            paths: new Parameters\Path\Paths(
                                id: new Parameters\Path\SchemaParameter(
                                    schema: new Schemas\String\Schema(),
                                    style: Parameters\Path\Style::Label,
                                ),
                            ),
                        ),
                    ),
                ),
                '/ping' => $ping,
            ],
            new Extensions(section: 'payments'),
        ),
        servers: new Servers(
            new Servers\Server(
                url: 'https://{region}.example.com/{ver}',
                description: 'Regional',
                variables: new Servers\Variables(
                    region: new Servers\Variable(
                        default: 'eu',
                        enum: new Schemas\String\Strings('eu', 'us'),
                        description: 'Region',
                        extensions: new Extensions(dns: 'geo'),
                    ),
                    ver: new Servers\Variable(default: 'v1'),
                ),
                extensions: new Extensions(internal: false),
            ),
        ),
        security: new Securities(new Securities\SecuritySchemes($read)),
        tags: new Tags(
            new Tags\Tag(
                name: 'payments',
                description: 'Everything about payments',
                externalDocs: new ExternalDocs(url: 'https://example.com/tags', description: 'More'),
                extensions: new Extensions(order: 1),
            ),
        ),
        externalDocs: new ExternalDocs(url: 'https://example.com/docs', description: 'The docs'),
        version: Version::V303,
        extensions: new Extensions(...['spec-filename' => 'kitchen-sink-30.json']),
    )];
})();
