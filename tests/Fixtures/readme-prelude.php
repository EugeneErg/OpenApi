<?php

declare(strict_types = 1);

require '__AUTOLOAD__';

use EugeneErg\OpenApi\Builder;
use EugeneErg\OpenApi\Components;
use EugeneErg\OpenApi\Components\Responses;
use EugeneErg\OpenApi\Components\Schemas;
use EugeneErg\OpenApi\Info;
use EugeneErg\OpenApi\Openapi;
use EugeneErg\OpenApi\Paths;

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
$openapi = new Openapi(info: $info, components: new Components(
    schemas: $schemas,
    responses: new Responses(NotFound: $notFound),
));
$builder = new Builder(...['openapi.json' => $openapi]);
