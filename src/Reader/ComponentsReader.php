<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Reader;

use EugeneErg\OpenApi\Components\Callbacks;
use EugeneErg\OpenApi\Components\Examples;
use EugeneErg\OpenApi\Components\Examples\Example;
use EugeneErg\OpenApi\Components\Headers;
use EugeneErg\OpenApi\Components\Links;
use EugeneErg\OpenApi\Components\Links\Link;
use EugeneErg\OpenApi\Components\Links\Link\Parameter as LinkParameter;
use EugeneErg\OpenApi\Components\Links\Link\Parameters as LinkParameters;
use EugeneErg\OpenApi\Components\Parameters\Abstract\AbstractSchemaParameter;
use EugeneErg\OpenApi\Components\Parameters as ParameterComponents;
use EugeneErg\OpenApi\Components\Parameters\ContentParameter;
use EugeneErg\OpenApi\Components\Parameters\Cookie;
use EugeneErg\OpenApi\Components\Parameters\CustomParameter;
use EugeneErg\OpenApi\Components\Parameters\Header;
use EugeneErg\OpenApi\Components\Parameters\In;
use EugeneErg\OpenApi\Components\Parameters\Parameter;
use EugeneErg\OpenApi\Components\Parameters\Parameters as OperationParameters;
use EugeneErg\OpenApi\Components\Parameters\Path;
use EugeneErg\OpenApi\Components\Parameters\Query;
use EugeneErg\OpenApi\Components\RequestBodies;
use EugeneErg\OpenApi\Components\RequestBodies\Content;
use EugeneErg\OpenApi\Components\RequestBodies\Contents;
use EugeneErg\OpenApi\Components\RequestBodies\Encoding;
use EugeneErg\OpenApi\Components\RequestBodies\Encodings;
use EugeneErg\OpenApi\Components\RequestBodies\EncodingStyle;
use EugeneErg\OpenApi\Components\RequestBodies\RequestBody;
use EugeneErg\OpenApi\Components\Responses;
use EugeneErg\OpenApi\Components\Responses\Response;
use EugeneErg\OpenApi\Components\Schemas\String\Strings;
use EugeneErg\OpenApi\Components\SecuritySchemes;
use EugeneErg\OpenApi\Components\SecuritySchemes\AbstractSecurityScheme;
use EugeneErg\OpenApi\Components\SecuritySchemes\ApiKeySecurity;
use EugeneErg\OpenApi\Components\SecuritySchemes\BasicHttpSecurityScheme;
use EugeneErg\OpenApi\Components\SecuritySchemes\BearerHttpSecurityScheme;
use EugeneErg\OpenApi\Components\SecuritySchemes\HttpSecurityScheme;
use EugeneErg\OpenApi\Components\SecuritySchemes\MutualTlsSecurityScheme;
use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security;
use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows;
use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows\AuthorizationCodeFlow;
use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows\ClientCredentialsFlow;
use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows\ImplicitFlow;
use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows\PasswordFlow;
use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows\Scope;
use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows\Scopes;
use EugeneErg\OpenApi\Components\SecuritySchemes\OpenIdConnectSecurityScheme;
use EugeneErg\OpenApi\Exceptions\InvalidDocumentOpenapiException;
use EugeneErg\OpenApi\PathItems;
use EugeneErg\OpenApi\Paths\Path as PathItem;
use EugeneErg\OpenApi\Reference;
use EugeneErg\OpenApi\Servers\Server;
use EugeneErg\OpenApi\Servers\Variable;
use EugeneErg\OpenApi\Servers\Variables;

use function sprintf;

/**
 * Reads the components section, except the schemas: those are SchemaReader's job.
 */
final readonly class ComponentsReader
{
    public function __construct(
        private Registry $registry,
        private SchemaReader $schemas,
    ) {
    }

    public function example(Node $node): Example
    {
        $registered = $this->registered($node);

        if ($registered instanceof Example) {
            return $registered;
        }

        return $this->buildExample($node);
    }

    public function buildExample(Node $node): Example
    {
        if ($node->has('$ref')) {
            return $this->referenced($node, Example::class);
        }

        return new Example(
            value: $this->schemas->readValue($node->get('value')),
            summary: $node->get('summary')->stringOrNull(),
            description: $node->get('description')->stringOrNull(),
            externalValue: $node->get('externalValue')->stringOrNull(),
            extensions: $node->extensions(),
        );
    }

    public function examples(Node $node): ?Examples
    {
        $items = [];

        foreach ($node->map() as $name => $item) {
            $items[$name] = $this->withOverrides($item, $this->example($item));
        }

        return $items === [] ? null : Examples::fromArray($items);
    }

    public function content(Node $node, string $mediaType): Content
    {
        if (!Contents::acceptsEncoding($mediaType)) {
            // "SHALL only apply ... when the media type is multipart or
            // application/x-www-form-urlencoded": here the keyword is read and dropped
            $node->dropped('encoding');
        }

        return new Content(
            schema: $node->has('schema') ? $this->schemas->read($node->get('schema')) : null,
            example: $this->schemas->readValue($node->get('example')),
            examples: $this->examples($node->get('examples')),
            // for other media types encoding changes nothing
            encoding: Contents::acceptsEncoding($mediaType) ? $this->encodings($node->get('encoding')) : null,
            extensions: $node->extensions(),
        );
    }

    public function contents(Node $node): ?Contents
    {
        $items = [];

        foreach ($node->map() as $mimeType => $item) {
            $items[$mimeType] = $this->content($item, (string) $mimeType);
        }

        return $items === [] ? null : Contents::fromArray($items);
    }

    public function requestBody(Node $node): RequestBody
    {
        $registered = $this->registered($node);

        if ($registered instanceof RequestBody) {
            return $registered;
        }

        return $this->buildRequestBody($node);
    }

    /**
     * An operation's request body: the object itself, or a reference to a component,
     * which in 3.1 may carry a description of its own.
     */
    public function operationRequestBody(Node $node): Reference|RequestBody
    {
        return $this->withOverrides($node, $this->requestBody($node));
    }

    public function buildRequestBody(Node $node): RequestBody
    {
        if ($node->has('$ref')) {
            return $this->referenced($node, RequestBody::class);
        }

        return new RequestBody(
            content: $this->contents($node->get('content'))
                ?? throw $node->get('content')->unexpected('at least one media type'),
            required: $node->get('required')->boolOr(false),
            description: $node->get('description')->stringOrNull(),
            extensions: $node->extensions(),
        );
    }

    public function response(Node $node): Response
    {
        $registered = $this->registered($node);

        if ($registered instanceof Response) {
            return $registered;
        }

        return $this->buildResponse($node);
    }

    public function buildResponse(Node $node): Response
    {
        if ($node->has('$ref')) {
            return $this->referenced($node, Response::class);
        }

        return new Response(
            description: $node->get('description')->string(),
            headers: $this->headers($node->get('headers')),
            content: $this->contents($node->get('content')),
            links: $this->links($node->get('links')),
            extensions: $node->extensions(),
        );
    }

    public function responses(Node $node): Responses
    {
        $items = [];

        foreach ($node->extensibleMap() as $code => $item) {
            $items[$code] = $this->withOverrides($item, $this->response($item));
        }

        return Responses::fromArray($items, $node->extensions());
    }

    public function headers(Node $node): ?Headers
    {
        $items = [];

        foreach ($node->map() as $name => $item) {
            $items[$name] = $this->withOverrides($item, $this->header($item));
        }

        return $items === [] ? null : Headers::fromArray($items);
    }

    public function header(Node $node): ContentParameter|Header\SchemaParameter
    {
        $registered = $this->registered($node);

        if ($registered instanceof ContentParameter || $registered instanceof Header\SchemaParameter) {
            return $registered;
        }

        return $this->buildHeader($node);
    }

    public function buildHeader(Node $node): ContentParameter|Header\SchemaParameter
    {
        if ($node->has('$ref')) {
            $result = $this->registry->resolve($this->registry->pointerOf($node->get('$ref')->string(), $node));

            return $result instanceof ContentParameter || $result instanceof Header\SchemaParameter
                ? $result
                : throw $node->unexpected('a header');
        }

        if ($node->has('content')) {
            return $this->contentParameter($node);
        }

        return new Header\SchemaParameter(
            schema: $this->schemas->read($node->get('schema')),
            explode: $node->get('explode')->boolOr(false),
            example: $this->schemas->readValue($node->get('example')),
            examples: $this->examples($node->get('examples')),
            description: $node->get('description')->stringOrNull(),
            required: $node->get('required')->boolOr(false),
            deprecated: $node->get('deprecated')->boolOr(false),
            extensions: $node->extensions(),
        );
    }

    public function link(Node $node): Link
    {
        $registered = $this->registered($node);

        if ($registered instanceof Link) {
            return $registered;
        }

        return $this->buildLink($node);
    }

    public function buildLink(Node $node): Link
    {
        if ($node->has('$ref')) {
            return $this->referenced($node, Link::class);
        }

        $parameters = [];

        foreach ($node->get('parameters')->map() as $name => $item) {
            $parameters[(string) $name] = LinkParameter::expression($item->string());
        }

        return new Link(
            operation: $this->registry->operation($node),
            parameters: $parameters === [] ? null : LinkParameters::fromArray($parameters),
            requestBody: $node->has('requestBody') ? $this->requestBody($node->get('requestBody')) : null,
            description: $node->get('description')->stringOrNull(),
            server: $this->server($node->get('server')),
            extensions: $node->extensions(),
        );
    }

    public function links(Node $node): ?Links
    {
        $items = [];

        foreach ($node->map() as $name => $item) {
            $items[$name] = $this->withOverrides($item, $this->link($item));
        }

        return $items === [] ? null : Links::fromArray($items);
    }

    public function callbacks(Node $node, PathsReader $paths): ?Callbacks
    {
        $items = [];

        foreach ($node->map() as $expression => $item) {
            $items[$expression] = $this->withOverrides($item, $this->callback($item, $paths));
        }

        return $items === [] ? null : Callbacks::fromArray($items);
    }

    /**
     * A value in callbacks is either the Callback Object itself or a reference to it.
     *
     * The keys of a Callback Object are runtime expressions, so `$ref` here must not
     * be read as one: the reference would become the name of a callback.
     */
    public function callback(Node $node, PathsReader $paths): PathItems
    {
        $registered = $this->registered($node);

        if ($registered instanceof PathItems) {
            return $registered;
        }

        if ($node->has('$ref')) {
            return $this->referenced($node, PathItems::class);
        }

        return $paths->callback($node);
    }

    /**
     * An operation's parameters: a flat list in the document, four containers here,
     * one per `in` value.
     */
    public function parameters(Node $node): ?OperationParameters
    {
        $headers = [];
        $cookies = [];
        $paths = [];
        $queries = [];

        foreach ($node->list() as $item) {
            [$in, $name, $parameter] = $this->parameter($item);

            // the container is chosen by the parameter itself, while what is stored
            // may be a reference to it: in 3.1 a reference has a description of its own
            $stored = $this->withOverrides($item, $parameter);

            if ($parameter instanceof ContentParameter) {
                if ($in === In::Header) {
                    $headers[$name] = $stored;
                } elseif ($in === In::Cookie) {
                    $cookies[$name] = $stored;
                } elseif ($in === In::Path) {
                    $paths[$name] = $stored;
                } else {
                    $queries[$name] = $stored;
                }

                continue;
            }

            if ($parameter instanceof Header\SchemaParameter) {
                $headers[$name] = $stored;
            } elseif ($parameter instanceof Cookie\SchemaParameter) {
                $cookies[$name] = $stored;
            } elseif ($parameter instanceof Path\SchemaParameter) {
                $paths[$name] = $stored;
            } else {
                $queries[$name] = $stored;
            }
        }

        if ($headers === [] && $cookies === [] && $paths === [] && $queries === []) {
            return null;
        }

        return new OperationParameters(
            headers: $headers === [] ? null : Header\Headers::fromArray($headers),
            cookies: $cookies === [] ? null : Cookie\Cookies::fromArray($cookies),
            paths: $paths === [] ? null : Path\Paths::fromArray($paths),
            queries: $queries === [] ? null : Query\Queries::fromArray($queries),
        );
    }

    /**
     * @return array{
     *     In,
     *     string,
     *     ContentParameter|Cookie\SchemaParameter|Header\SchemaParameter|Path\SchemaParameter|Query\SchemaParameter
     * }
     */
    public function parameter(Node $node): array
    {
        if ($node->has('$ref')) {
            $pointer = $this->registry->pointerOf($node->get('$ref')->string(), $node);
            $result = $this->registry->resolve($pointer);

            if (!$result instanceof Parameter) {
                throw $node->unexpected('a parameter');
            }

            $source = $this->registry->node($pointer) ?? throw $node->unexpected('a parameter');
            $parameter = $result->parameter;

            return [
                $this->inOf($source),
                $result->name,
                // CustomParameter wraps "in + content", and the parameter itself is what callers need
                $parameter instanceof CustomParameter ? $parameter->contentParameter : $parameter,
            ];
        }

        $in = $this->inOf($node);
        $name = $node->get('name')->string();

        if ($node->has('content')) {
            return [$in, $name, $this->contentParameter($node)];
        }

        $shared = [
            'schema' => $this->schemas->read($node->get('schema')),
            'example' => $this->schemas->readValue($node->get('example')),
            'examples' => $this->examples($node->get('examples')),
            'description' => $node->get('description')->stringOrNull(),
            'deprecated' => $node->get('deprecated')->boolOr(false),
            'extensions' => $node->extensions(),
        ];

        // explode is not set: its default depends on the style, and the parameter class knows it
        $explode = $node->has('explode') ? $node->get('explode')->bool() : null;

        // "If the parameter location is \"path\" ... its value MUST be true":
        // for a path parameter the keyword is read only to be checked
        if ($in === In::Path && $node->get('required')->boolOr(true) === false) {
            throw new InvalidDocumentOpenapiException(sprintf(
                '%s: a path parameter is always required, so "required" cannot be false.',
                $node->path,
            ));
        }

        return [$in, $name, match ($in) {
            In::Query => new Query\SchemaParameter(
                ...$shared,
                explode: $explode,
                allowEmptyValue: $node->get('allowEmptyValue')->boolOr(false),
                allowReserved: $node->get('allowReserved')->boolOr(false),
                required: $node->get('required')->boolOr(false),
                style: $this->style(Query\Style::class, $node) ?? Query\Style::Form,
            ),
            In::Path => new Path\SchemaParameter(
                ...$shared,
                explode: $explode,
                style: $this->style(Path\Style::class, $node) ?? Path\Style::Simple,
            ),
            In::Header => new Header\SchemaParameter(
                ...$shared,
                explode: $explode,
                required: $node->get('required')->boolOr(false),
            ),
            In::Cookie => new Cookie\SchemaParameter(
                ...$shared,
                explode: $explode,
                required: $node->get('required')->boolOr(false),
            ),
        }];
    }

    public function parameterComponent(Node $node): Parameter
    {
        $registered = $this->registered($node);

        if ($registered instanceof Parameter) {
            return $registered;
        }

        return $this->buildParameterComponent($node);
    }

    public function buildParameterComponent(Node $node): Parameter
    {
        [, $name, $parameter] = $this->parameter($node);

        if ($parameter instanceof ContentParameter) {
            throw $node->unexpected('a schema parameter: content parameters are stored per usage');
        }

        return new Parameter($name, $parameter);
    }

    public function parameterComponents(Node $node): ?ParameterComponents
    {
        $items = [];

        foreach ($node->map() as $key => $item) {
            $items[$key] = $this->parameterComponent($item);
        }

        return $items === [] ? null : ParameterComponents::fromArray($items);
    }

    public function requestBodies(Node $node): ?RequestBodies
    {
        $items = [];

        foreach ($node->map() as $name => $item) {
            $items[$name] = $this->requestBody($item);
        }

        return $items === [] ? null : RequestBodies::fromArray($items);
    }

    public function securitySchemes(Node $node): ?SecuritySchemes
    {
        $items = [];

        foreach ($node->map() as $name => $item) {
            $items[$name] = $this->securityScheme($item);
        }

        return $items === [] ? null : SecuritySchemes::fromArray($items);
    }

    public function securityScheme(Node $node): AbstractSecurityScheme
    {
        $registered = $this->registered($node);

        if ($registered instanceof AbstractSecurityScheme) {
            return $registered;
        }

        return $this->buildSecurityScheme($node);
    }

    public function buildSecurityScheme(Node $node): AbstractSecurityScheme
    {
        $description = $node->get('description')->stringOrNull();

        return match ($node->get('type')->string()) {
            'apiKey' => new ApiKeySecurity\Scheme(
                name: $node->get('name')->string(),
                in: ApiKeySecurity\In::tryFrom($node->get('in')->string())
                    ?? throw $node->get('in')->unexpected('header, query or cookie'),
                description: $description,
                extensions: $node->extensions(),
            ),
            // an HTTP scheme name is case-insensitive (RFC 7235): Bearer and bearer are one
            'http' => match (strtolower($scheme = $node->get('scheme')->string())) {
                'bearer' => new BearerHttpSecurityScheme(
                    $node->get('bearerFormat')->stringOrNull(),
                    $description,
                    $node->extensions(),
                ),
                'basic' => new BasicHttpSecurityScheme($description, $node->extensions()),
                default => new HttpSecurityScheme($scheme, $description, $node->extensions()),
            },
            'oauth2' => new Oauth2Security\Scheme(
                $this->flows($node->get('flows')),
                $description,
                $node->extensions(),
            ),
            'openIdConnect' => new OpenIdConnectSecurityScheme(
                $node->get('openIdConnectUrl')->string(),
                $description,
                $node->extensions(),
            ),
            'mutualTLS' => new MutualTlsSecurityScheme($description, $node->extensions()),
            default => throw $node->get('type')->unexpected('a known security scheme type'),
        };
    }

    public function server(Node $node): ?Server
    {
        if ($node->isMissing()) {
            return null;
        }

        $variables = [];

        foreach ($node->get('variables')->map() as $name => $variable) {
            $enum = $variable->get('enum');

            $variables[(string) $name] = new Variable(
                default: $variable->get('default')->string(),
                enum: $enum->isMissing() ? null : new Strings(...$enum->strings()),
                description: $variable->get('description')->stringOrNull(),
                extensions: $variable->extensions(),
            );
        }

        return new Server(
            url: $node->get('url')->string(),
            description: $node->get('description')->stringOrNull(),
            variables: $variables === [] ? null : Variables::fromArray($variables),
            extensions: $node->extensions(),
        );
    }

    /**
     * The component already built for this node, if the registry declares one.
     *
     * Without it the object would be built twice — once through the reference, once in
     * place — and writing the document back would not deduplicate it again.
     */
    private function registered(Node $node): ?object
    {
        $pointer = $this->registry->pointerOfNode($node);

        return $pointer !== null && $this->registry->has($pointer) ? $this->registry->resolve($pointer) : null;
    }

    /**
     * A reference with its own summary or description (3.1) is a Reference Object rather
     * than just its target: the component's description is overridden at the use site.
     */
    /**
     * @template T of AbstractSchemaParameter|ContentParameter|Example|Link|PathItem|PathItems|RequestBody|Response
     *
     * @param T $target
     *
     * @return Reference|T
     */
    private function withOverrides(Node $node, object $target): object
    {
        if (!$node->has('$ref')) {
            return $target;
        }

        $summary = $node->get('summary')->stringOrNull();
        $description = $node->get('description')->stringOrNull();

        return $summary === null && $description === null
            ? $target
            : new Reference($target, $summary, $description);
    }

    private function flows(Node $node): Flows
    {
        $implicit = $node->get('implicit');
        $password = $node->get('password');
        $clientCredentials = $node->get('clientCredentials');
        $authorizationCode = $node->get('authorizationCode');

        $passwordFlow = $password->isMissing() ? null : new PasswordFlow(
            tokenUrl: $password->get('tokenUrl')->string(),
            scopes: $this->scopes($password->get('scopes')),
            refreshUrl: $password->get('refreshUrl')->stringOrNull(),
            extensions: $password->extensions(),
        );
        $clientFlow = $clientCredentials->isMissing() ? null : new ClientCredentialsFlow(
            tokenUrl: $clientCredentials->get('tokenUrl')->string(),
            scopes: $this->scopes($clientCredentials->get('scopes')),
            refreshUrl: $clientCredentials->get('refreshUrl')->stringOrNull(),
            extensions: $clientCredentials->extensions(),
        );
        $codeFlow = $authorizationCode->isMissing() ? null : new AuthorizationCodeFlow(
            authorizationUrl: $authorizationCode->get('authorizationUrl')->string(),
            tokenUrl: $authorizationCode->get('tokenUrl')->string(),
            scopes: $this->scopes($authorizationCode->get('scopes')),
            refreshUrl: $authorizationCode->get('refreshUrl')->stringOrNull(),
            extensions: $authorizationCode->extensions(),
        );

        if (!$implicit->isMissing()) {
            return Flows::createImplicit(
                new ImplicitFlow(
                    authorizationUrl: $implicit->get('authorizationUrl')->string(),
                    scopes: $this->scopes($implicit->get('scopes')),
                    refreshUrl: $implicit->get('refreshUrl')->stringOrNull(),
                    extensions: $implicit->extensions(),
                ),
                $passwordFlow,
                $clientFlow,
                $codeFlow,
                $node->extensions(),
            );
        }

        if ($passwordFlow !== null) {
            return Flows::createPassword($passwordFlow, $clientFlow, $codeFlow, $node->extensions());
        }

        if ($clientFlow !== null) {
            return Flows::createClientCredentials($clientFlow, $codeFlow, $node->extensions());
        }

        return Flows::createAuthorizationCode(
            $codeFlow ?? throw $node->unexpected('at least one oauth2 flow'),
            $node->extensions(),
        );
    }

    private function scopes(Node $node): Scopes
    {
        $items = [];

        foreach ($node->map() as $name => $item) {
            $items[$name] = new Scope($item->string());
        }

        return Scopes::fromArray($items);
    }

    private function encodings(Node $node): ?Encodings
    {
        $items = [];

        foreach ($node->map() as $property => $item) {
            $items[$property] = new Encoding(
                contentType: $item->get('contentType')->stringOrNull(),
                explode: $item->has('explode') ? $item->get('explode')->bool() : null,
                allowReserved: $item->has('allowReserved') ? $item->get('allowReserved')->bool() : null,
                style: $this->style(EncodingStyle::class, $item),
                headers: $this->headers($item->get('headers')),
                extensions: $item->extensions(),
            );
        }

        return $items === [] ? null : Encodings::fromArray($items);
    }

    private function contentParameter(Node $node): ContentParameter
    {
        foreach ($node->get('content')->map() as $mimeType => $media) {
            return $this->buildContentParameter($node, (string) $mimeType, $media);
        }

        throw $node->get('content')->unexpected('one media type');
    }

    private function buildContentParameter(Node $node, string $mimeType, Node $media): ContentParameter
    {
        return new ContentParameter(
            mimeType: $mimeType,
            content: $this->content($media, $mimeType),
            description: $node->get('description')->stringOrNull(),
            required: $node->get('required')->boolOr(false),
            deprecated: $node->get('deprecated')->boolOr(false),
            extensions: $node->extensions(),
        );
    }

    private function inOf(Node $node): In
    {
        return In::tryFrom($node->get('in')->string())
            ?? throw $node->get('in')->unexpected('query, path, header or cookie');
    }

    /**
     * @template T of \BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return null|T
     */
    private function style(string $enum, Node $node): ?object
    {
        $value = $node->get('style')->stringOrNull();

        if ($value === null) {
            return null;
        }

        return $enum::tryFrom($value) ?? throw $node->get('style')->unexpected('a known style');
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $expected
     *
     * @return T
     */
    private function referenced(Node $node, string $expected): object
    {
        $result = $this->registry->resolve($this->registry->pointerOf($node->get('$ref')->string(), $node));

        return $result instanceof $expected
            ? $result
            : throw new InvalidDocumentOpenapiException(sprintf(
                '%s: reference points at %s, but %s was expected.',
                $node->path,
                $result::class,
                $expected,
            ));
    }
}
