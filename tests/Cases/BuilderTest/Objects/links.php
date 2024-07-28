<?php

declare(strict_types = 1);

use EugeneErg\OpenApi\Components;
use EugeneErg\OpenApi\Components\Links\Link\Parameter;
use EugeneErg\OpenApi\Components\Parameters;
use EugeneErg\OpenApi\Components\RequestBodies;
use EugeneErg\OpenApi\Components\Schemas;
use EugeneErg\OpenApi\Components\Responses;
use EugeneErg\OpenApi\Info;
use EugeneErg\OpenApi\Openapi;
use EugeneErg\OpenApi\Paths;
use EugeneErg\OpenApi\Servers;

$user = new Schemas\Object\Schema(
    properties: new Schemas\Object\Properties(
        id: new Schemas\Object\Property(
            schema: new Schemas\Integer\Schema(
                format: Schemas\Integer\Format::Int64,
            ),
            required: true,
        ),
        username: new Schemas\Object\Property(
            schema: new Schemas\String\Schema(),
            required: true,
        ),
        email: new Schemas\Object\Property(
            schema: new Schemas\String\Schema(
                format: Schemas\String\Format::Email,
            ),
            required: true,
        ),
    ),
);

$order = new Schemas\Object\Schema(
    properties: new Schemas\Object\Properties(
        id: new Schemas\Object\Property(
            schema: new Schemas\Integer\Schema(
                format: Schemas\Integer\Format::Int64,
            ),
            required: true,
        ),
        total: new Schemas\Object\Property(
            schema: new Schemas\Number\Schema(
                format: Schemas\Number\Format::Float,
            ),
            required: true,
        ),
        userId: new Schemas\Object\Property(
            schema: new Schemas\Integer\Schema(
                format: Schemas\Integer\Format::Int64,
            ),
            required: true,
        ),
    ),
);

$error = new Schemas\Object\Schema(
    properties: new Schemas\Object\Properties(
        code: new Schemas\Object\Property(
            schema: new Schemas\Integer\Schema(
                format: Schemas\Integer\Format::Int32,
            ),
            required: true,
        ),
        message: new Schemas\Object\Property(
            schema: new Schemas\String\Schema(),
            required: true,
        ),
    ),
);

$listUserOrders = new Paths\Operation(
    responses: new Responses(
        x200: new Responses\Response(
            description: 'A list of orders',
            content: new RequestBodies\Contents(...[
                'application/json' => new RequestBodies\Content(
                    schema: new Schemas\Array\Schema(
                        items: $order,
                    ),
                ),
            ]),
        ),
        x404: new Responses\Response(
            description: 'Orders not found',
            content: new RequestBodies\Contents(...[
                'application/json' => new RequestBodies\Content(
                    schema: $error,
                ),
            ]),
        ),
    ),
    summary: 'List orders of a user',
    id: 'listUserOrders',
    parameters: new Parameters\Parameters(
        paths: new Parameters\Path\Paths(
            userId: new Parameters\Path\SchemaParameter(
                schema: new Schemas\Integer\Schema(
                    format: Schemas\Integer\Format::Int64,
                ),
                description: 'ID of the user whose orders to list',
            ),
        ),
    ),
);

$userResponse = new Responses\Response(
    description: 'A single user',
    content: new RequestBodies\Contents(...[
        'application/json' => new RequestBodies\Content(
            schema: $user,
        ),
    ]),
    links: new Components\Links(
        UserOrders: new Components\Links\Link(
            operation: $listUserOrders,
            parameters: new Components\Links\Link\Parameters(
                userId: Parameter::responseBody('id'),
            ),
            description: 'The orders of the retrieved user',
        ),
    ),
);

return [
    'links.yaml' => new Openapi(
        new Info(
            title: 'Linked API',
            version: '1.0.0',
        ),
        components: new Components(
            schemas: new Schemas\Untyped\Schemas(
                User: $user,
                Order: $order,
                Error: $error,
            ),
            responses: new Responses(
                UserResponse: $userResponse,
            ),
            links: new Components\Links(
                UserOrders: new Components\Links\Link(
                    operation: $listUserOrders,
                    parameters: new Components\Links\Link\Parameters(
                        userId: Parameter::responseBody('id'),
                    ),
                    description: 'The orders of the retrieved user',
                ),
            ),
        ),
        paths: new Paths(...[
            '/users/{id}' => new Paths\Path(
                get: new Paths\Operation(
                    responses: new Responses(
                        x200: $userResponse,
                        x404: new Responses\Response(
                            description: 'User not found',
                            content: new RequestBodies\Contents(...[
                                'application/json' => new RequestBodies\Content(
                                    schema: $error,
                                ),
                            ]),
                        ),
                    ),
                    summary: 'Get a user by ID',
                    id: 'getUserById',
                    parameters: new Parameters\Parameters(
                        paths: new Parameters\Path\Paths(
                            id: new Parameters\Path\SchemaParameter(
                                schema: new Schemas\Integer\Schema(
                                    format: Schemas\Integer\Format::Int64,
                                ),
                                description: 'ID of the user to retrieve',
                            ),
                        ),
                    ),
                ),
            ),
            '/users/{userId}/orders' => new Paths\Path(
                get: $listUserOrders,
            ),
        ]),
        servers: new Servers(
            new Servers\Server(
                url: 'https://api.example.com/v1',
                description: 'Main server',
            ),
        )
    ),
];