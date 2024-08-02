<?php

declare(strict_types = 1);

use EugeneErg\OpenApi\Components;
use EugeneErg\OpenApi\Components\Parameters;
use EugeneErg\OpenApi\Components\RequestBodies;
use EugeneErg\OpenApi\Components\Responses;
use EugeneErg\OpenApi\Components\Schemas;
use EugeneErg\OpenApi\Components\SecuritySchemes;
use EugeneErg\OpenApi\Info;
use EugeneErg\OpenApi\Openapi;
use EugeneErg\OpenApi\Paths;
use EugeneErg\OpenApi\Securities;
use EugeneErg\OpenApi\Servers;

$schemaError = new Schemas\Object\Schema(
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

$bearerAuth = new SecuritySchemes\BearerHttpSecurityScheme(
    format: 'JWT',
);

$authHeader = new Parameters\Parameter(
    name: 'Authorization',
    parameter: new Parameters\Header\SchemaParameter(
        schema: new Schemas\String\Schema(),
        description: 'Bearer token for authentication',
        required: true,
    ),
);

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
        role:  new Schemas\Object\Property(
            schema: new Schemas\String\EnumSchema(new Schemas\String\Strings(
                'user',
                'admin',
            )),
        ),
    ),
);

$userId = new Parameters\Parameter(
    name: 'id',
    parameter: new Parameters\Path\SchemaParameter(
        schema: new Schemas\Integer\Schema(
            format: Schemas\Integer\Format::Int64,
        ),
        description: 'ID of the user',
    ),
);

$generalError = new Responses\Response(
    description: 'General error.',
    content: new RequestBodies\Contents(...[
        'application/json' => new RequestBodies\Content(
            schema: $schemaError,
        ),
    ]),
);

$notFound = new Responses\Response(
    description: 'Entity not found.',
);

$post = new Schemas\Object\Schema(
    properties: new Schemas\Object\Properties(
        id: new Schemas\Object\Property(
            schema: new Schemas\Integer\Schema(
                format: Schemas\Integer\Format::Int64,
            ),
            required: true,
        ),
        title: new Schemas\Object\Property(
            schema: new Schemas\String\Schema(),
            required: true,
        ),
        content: new Schemas\Object\Property(
            schema: new Schemas\String\Schema(),
            required: true,
        ),
        authorId: new Schemas\Object\Property(
            schema: new Schemas\Integer\Schema(
                format: Schemas\Integer\Format::Int64,
            ),
            required: true,
        ),
    ),
);

$postId = new Parameters\Parameter(
    name: 'id',
    parameter: new Parameters\Path\SchemaParameter(
        schema: new Schemas\Integer\Schema(
            format: Schemas\Integer\Format::Int64,
        ),
        description: 'ID of the post',
    ),
);

return [
    'file.yaml' => new Openapi(
        info: new Info(
            title: 'Complex API',
            version: '1.0.0',
        ),
        paths: new Paths(...[
            '/users' => new Paths\Path(
                get: new Paths\Operation(
                    responses: new Responses(
                        x200: new Responses\Response(
                            description: 'A paged array of users',
                            content: new RequestBodies\Contents(...[
                                'application/json' => new RequestBodies\Content(
                                    schema: new Schemas\Array\Schema(
                                        items: $user,
                                    ),
                                ),
                            ]),
                        ),
                        default: $generalError,
                    ),
                    summary: 'List all users',
                    id: 'listUsers',
                    parameters: new Parameters\Parameters(
                        headers: new Parameters\Header\Headers($authHeader->parameter),
                    ),
                ),
                post: new Paths\Operation(
                    responses: new Responses(
                        x201: new Responses\Response(
                            description: 'User created',
                            content: new RequestBodies\Contents(...[
                                'application/json' => new RequestBodies\Content(
                                    schema: $user,
                                ),
                            ]),
                        ),
                        default: $generalError,
                    ),
                    summary: 'Create a user',
                    id: 'createUser',
                    parameters: new Parameters\Parameters(
                        headers: new Parameters\Header\Headers($authHeader->parameter),
                    ),
                    requestBody: new RequestBodies\RequestBody(
                        content: new RequestBodies\Contents(...[
                            'application/json' => new RequestBodies\Content(
                                schema: $user,
                            ),
                        ]),
                    ),
                ),
            ),
            '/users/{id}' => new Paths\Path(
                get: new Paths\Operation(
                    responses: new Responses(
                        x200: new Responses\Response(
                            description: 'User details',
                            content: new RequestBodies\Contents(...[
                                'application/json' => new RequestBodies\Content(
                                    schema: $user,
                                ),
                            ]),
                        ),
                        x404: $notFound,
                        default: $generalError,
                    ),
                    summary: 'Get a specific user',
                    id: 'getUser',
                    parameters: new Parameters\Parameters(
                        headers: new Parameters\Header\Headers($authHeader->parameter),
                        paths: new Parameters\Path\Paths($userId->parameter),
                    ),
                ),
                delete: new Paths\Operation(
                    responses: new Responses(
                        x204: new Responses\Response(
                            description: 'User deleted',
                        ),
                        x404: $notFound,
                        default: $generalError,
                    ),
                    summary: 'Delete a user',
                    id: 'deleteUser',
                    parameters: new Parameters\Parameters(
                        headers: new Parameters\Header\Headers($authHeader->parameter),
                        paths: new Parameters\Path\Paths($userId->parameter),
                    ),
                ),
            ),
            '/posts' => new Paths\Path(
                get: new Paths\Operation(
                    responses: new Responses(
                        x200: new Responses\Response(
                            description: 'A paged array of posts',
                            content: new RequestBodies\Contents(...[
                                'application/json' => new RequestBodies\Content(
                                    schema: new Schemas\Array\Schema(
                                        items: $post,
                                    ),
                                ),
                            ]),
                        ),
                        default: $generalError,
                    ),
                    summary: 'List all posts',
                    id: 'listPosts',
                    parameters: new Parameters\Parameters(
                        headers: new Parameters\Header\Headers($authHeader->parameter),
                    ),
                ),
                post: new Paths\Operation(
                    responses: new Responses(
                        x201: new Responses\Response(
                            description: 'Post created',
                            content: new RequestBodies\Contents(...[
                                'application/json' => new RequestBodies\Content(
                                    schema: $post,
                                ),
                            ]),
                        ),
                        default: $generalError,
                    ),
                    summary: 'Create a post',
                    id: 'createPost',
                    parameters: new Parameters\Parameters(
                        headers: new Parameters\Header\Headers($authHeader->parameter),
                    ),
                    requestBody: new RequestBodies\RequestBody(
                        content: new RequestBodies\Contents(...[
                            'application/json' => new RequestBodies\Content(
                                schema: $post,
                            ),
                        ]),
                    ),
                ),
            ),
            '/posts/{id}' => new Paths\Path(
                get: new Paths\Operation(
                    responses: new Responses(
                        x200: new Responses\Response(
                            description: 'Post details',
                            content: new RequestBodies\Contents(...[
                                'application/json' => new RequestBodies\Content(
                                    schema: $post,
                                ),
                            ]),
                        ),
                        x404: $notFound,
                        default: $generalError,
                    ),
                    summary: 'Get a specific post',
                    id: 'getPost',
                    parameters: new Parameters\Parameters(
                        headers: new Parameters\Header\Headers($authHeader->parameter),
                        paths: new Parameters\Path\Paths($postId->parameter),
                    ),
                ),
                delete: new Paths\Operation(
                    responses: new Responses(
                        x204: new Responses\Response(
                            description: 'Post deleted',
                        ),
                        x404: $notFound,
                        default: $generalError,
                    ),
                    summary: 'Delete a post',
                    id: 'deletePost',
                    parameters: new Parameters\Parameters(
                        headers: new Parameters\Header\Headers($authHeader->parameter),
                        paths: new Parameters\Path\Paths($postId->parameter),
                    ),
                ),
            ),
        ]),
        components: new Components(
            schemas: new Schemas\Untyped\Schemas(
                User: $user,
                Post: $post,
                Error: $schemaError,
            ),
            responses: new Responses(
                NotFound: $notFound,
                IllegalInput: new Responses\Response(
                    description: 'Illegal input for operation.',
                ),
                GeneralError: $generalError,
            ),
            parameters: new Parameters(
                userId: $userId,
                postId: $postId,
                authHeader: $authHeader,
            ),
            securitySchemes: new SecuritySchemes(
                bearerAuth: $bearerAuth,
            ),
        ),
        security: new Securities(
            new Securities\SecuritySchemes(
                $bearerAuth,
            ),
        ),
        servers: new Servers(
            new Servers\Server(
                url: 'https://api.example.com/v1',
                description: 'Main (production) server',
            ),
        ),
    ),
];
