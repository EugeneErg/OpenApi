<?php
require '__AUTOLOAD__';

use EugeneErg\OpenApi\Builder;
use EugeneErg\OpenApi\Components;
use EugeneErg\OpenApi\Components\Examples;
use EugeneErg\OpenApi\Components\Examples\Example;
use EugeneErg\OpenApi\Components\Parameters;
use EugeneErg\OpenApi\Components\RequestBodies;
use EugeneErg\OpenApi\Components\Responses;
use EugeneErg\OpenApi\Components\Schemas;
use EugeneErg\OpenApi\Components\SecuritySchemes;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Discriminator;
use EugeneErg\OpenApi\Components\Schemas\String\Strings;
use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security;
use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows\AuthorizationCodeFlow;
use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows\Scope;
use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows\Scopes;
use EugeneErg\OpenApi\Extensions;
use EugeneErg\OpenApi\Components\Links;
use EugeneErg\OpenApi\Components\Links\Link;
use EugeneErg\OpenApi\Paths\DeferredOperation;
use EugeneErg\OpenApi\Info;
use EugeneErg\OpenApi\Openapi;
use EugeneErg\OpenApi\Reader;
use EugeneErg\OpenApi\Serialization\DecoderInterface;
use EugeneErg\OpenApi\Serialization\Structure;
use EugeneErg\OpenApi\Serialization\YamlDecoder;
use EugeneErg\OpenApi\PathItems;
use EugeneErg\OpenApi\Paths;
use EugeneErg\OpenApi\Reference;
use EugeneErg\OpenApi\Securities;
use EugeneErg\OpenApi\Serialization\JsonEncoder;
use EugeneErg\OpenApi\Serialization\YamlEncoder;
use EugeneErg\OpenApi\Servers;
use EugeneErg\OpenApi\Version;

$info = new Info(title: 'Example API', version: '1.0.0');
$generalError = new Responses\Response(description: 'Error');
$notFound = new Responses\Response(description: 'Not found');
$dog = new Schemas\Object\Schema(properties: new Schemas\Object\Properties());
$cat = new Schemas\Object\Schema(properties: new Schemas\Object\Properties());
$schemas = new Schemas\Untyped\Schemas(Dog: $dog, Cat: $cat);
$paths = new Paths();
$onUserCreated = new Paths\Operation(
    responses: new Responses(x200: new Responses\Response(description: 'ok')),
    id: 'onUserCreated',
);
$content = (string) file_get_contents('openapi.json');
$properties = new Schemas\Object\Properties();
$responses = new Responses(x200: new Responses\Response(description: 'ok'));
$upload = new RequestBodies\RequestBody(content: RequestBodies\Contents::fromArray([
    'application/json' => new RequestBodies\Content(schema: new Schemas\String\Schema()),
]));
$onEvent = PathItems::fromArray([
    '{$request.body#/callbackUrl}' => new Paths\Path(post: new Paths\Operation(responses: $responses)),
]);
$pageQuery = new Parameters\Query\SchemaParameter(schema: new Schemas\Integer\Schema());
$openapi = new Openapi(info: $info, components: new Components(
    schemas: $schemas,
    responses: new Responses(NotFound: $notFound),
));
$builder = new Builder(...['openapi.json' => $openapi]);
