<?php

declare(strict_types = 1);

use EugeneErg\OpenApi\Components;
use EugeneErg\OpenApi\Components\Parameters;
use EugeneErg\OpenApi\Components\RequestBodies;
use EugeneErg\OpenApi\Components\Schemas;
use EugeneErg\OpenApi\Components\Responses;
use EugeneErg\OpenApi\Info;
use EugeneErg\OpenApi\Openapi;
use EugeneErg\OpenApi\Paths;

$info = new Info(title: 'Example API', version: '1.0.0');

$generalErrorContentSchema = new Schemas\Object\Schema(
    properties: new Schemas\Object\Properties(
        code:  new Schemas\Object\Property(
            new Schemas\Integer\Schema(
                format: Schemas\Integer\Format::Int32,
            ),
            required: true,
        ),
        message : new Schemas\Object\Property(
            schema: new Schemas\String\Schema(),
            required: true,
        ),
    ),
);
global $userSchema;
$userSchema = new Schemas\Object\Schema(
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

$generalError = new Responses\Response(
    description: 'General error.',
    content: new RequestBodies\Contents(...[
        'application/json' => new RequestBodies\Content(
            schema: $generalErrorContentSchema,
        ),
    ]),
);

$notFound = new Responses\Response(description: 'Entity not found.');

$postSchema = new Schemas\Object\Schema(
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

global $components;
$components = new Openapi(
    info: $info,
    components: new Components(
        schemas: new Schemas\Untyped\Schemas(
            User: $userSchema,
            Post: $postSchema,
            Error: $generalErrorContentSchema,
        ),
        responses: new Responses(
            NotFound: $notFound,
            IllegalInput: new Responses\Response(
                description: 'Illegal input for operation.',
            ),
            GeneralError: $generalError,
        ),
    ),
);

$paths = new Openapi(
    info: $info,
    paths: new Paths(...[
        '/users' => new Paths\Path(
            get: new Paths\Operation(
                responses: new Responses(
                    x200: new Responses\Response(
                        description: 'A paged array of users',
                        content: new RequestBodies\Contents(...[
                            'application/json' => new RequestBodies\Content(
                                schema: new Schemas\Array\Schema(
                                    items: $userSchema,
                                ),
                            ),
                        ]),
                    ),
                    default: $generalError,
                ),
                summary: 'List all users',
                id: 'listUsers',
            ),
            post: new Paths\Operation(
                responses: new Responses(
                    x201: new Responses\Response(
                        description: 'User created',
                        content: new RequestBodies\Contents(...[
                            'application/json' => new RequestBodies\Content(
                                schema: $userSchema,
                            ),
                        ]),
                    ),
                    default: $generalError,
                ),
                summary: 'Create a user',
                id: 'createUser',
            ),
        ),
        '/users/{id}' => new Paths\Path(
            get: new Paths\Operation(
                responses: new Responses(
                    x200: new Responses\Response(
                        description: 'User details',
                        content: new RequestBodies\Contents(...[
                            'application/json' => new RequestBodies\Content(
                                schema: $userSchema,
                            )
                        ]),
                    ),
                    x404: $notFound,
                    default: $generalError,
                ),
                summary: 'Info for a specific user',
                id: 'getUser',
                parameters: new Parameters\Parameters(
                    paths: new Parameters\Path\Paths(
                        id: new Parameters\Path\SchemaParameter(
                            new Schemas\Integer\Schema(
                                format: Schemas\Integer\Format::Int64,
                            ),
                        ),
                    ),
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
                                    items: $postSchema,
                                ),
                            ),
                        ]),
                    ),
                    default: $generalError,
                ),
                summary: 'List all posts',
                id: 'listPosts',
            ),
            post: new Paths\Operation(
                responses: new Responses(
                    x201: new Responses\Response(
                        description: 'Post created',
                        content: new RequestBodies\Contents(...[
                            'application/json' => new RequestBodies\Content(
                                schema: $postSchema,
                            ),
                        ]),
                    ),
                    default: $generalError,
                ),
                summary: 'Create a post',
                id: 'createPost',
            ),
        ),
        '/posts/{id}' => new Paths\Path(
            get: new Paths\Operation(
                responses: new Responses(
                    x200: new Responses\Response(
                        description: 'Post details',
                        content: new RequestBodies\Contents(...[
                            'application/json' => new RequestBodies\Content(
                                schema: $postSchema,
                            ),
                        ]),
                    ),
                    x404: $notFound,
                    default: $generalError,
                ),
                summary: 'Info for a specific post',
                id: 'getPost',
                parameters: new Parameters\Parameters(
                    paths: new Parameters\Path\Paths(
                        id: new Parameters\Path\SchemaParameter(
                            schema: new Schemas\Integer\Schema(
                                format: Schemas\Integer\Format::Int64,
                            ),
                        ),
                    ),
                ),
            ),
        ),
    ]),
);

return [
    'components.yaml' => $components,
    'paths.yaml' => $paths,
];
