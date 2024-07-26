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
use EugeneErg\OpenApi\Servers;

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

$categoryFilter = new Parameters\Query\SchemaParameter(
    schema: new Schemas\String\Schema(),
    description: 'Filter by category',
    required: false,
);

$priceRange = new Parameters\Query\SchemaParameter(
    schema: new Schemas\String\Schema(
        example: new Schemas\String\Value('10.00-100.00'),
        pattern: "^\\d+\\.\\d{2}-\\d+\\.\\d{2}$",
    ),
    description: 'Filter by price range',
    required: false,
);

$product = new Schemas\Object\Schema(
    properties: new Schemas\Object\Properties(
        id: new Schemas\Object\Property(
            schema: new Schemas\Integer\Schema(
                format: Schemas\Integer\Format::Int64,
            ),
            required: true,
        ),
        name: new Schemas\Object\Property(
            schema: new Schemas\String\Schema(),
            required: true,
        ),
        description: new Schemas\Object\Property(
            schema: new Schemas\String\Schema(),
        ),
        price: new Schemas\Object\Property(
            schema: new Schemas\Number\Schema(
                format: Schemas\Number\Format::Float,
            ),
            required: true,
        ),
        category: new Schemas\Object\Property(
            schema: new Schemas\String\Schema(),
        ),
    ),
);

$generalError = new Responses\Response(
    description: 'General error.',
    content: new RequestBodies\Contents(...[
        'application/json' => new RequestBodies\Content(
            schema: $error,
        ),
    ]),
);

$notFound = new Responses\Response(
    description: 'Entity not found.',
);

$category = new Schemas\Object\Schema(
    properties: new Schemas\Object\Properties(
        id:  new Schemas\Object\Property(
            schema: new Schemas\Integer\Schema(
                format: Schemas\Integer\Format::Int64,
            ),
            required: true,
        ),
        name: new Schemas\Object\Property(
            schema: new Schemas\String\Schema(),
            required: true,
        ),
    ),
);

return [
    'case3.yaml' => new Openapi(
        info: new Info(
            title: 'Advanced API',
            version: '1.0.0',
        ),
        paths: new Paths(...[
            '/products' => new Paths\Path(
                get: new Paths\Operation(
                    responses: new Responses(
                        x200: new Responses\Response(
                            description: 'A list of products',
                            content: new RequestBodies\Contents(...[
                                'application/json' => new RequestBodies\Content(
                                    schema: new Schemas\Array\Schema(
                                        items: $product,
                                    ),
                                ),
                            ]),
                        ),
                        default: $generalError,
                    ),
                    summary: 'List all products',
                    id: 'listProducts',
                    parameters: new Parameters\Parameters(
                        queries: new Parameters\Query\Queries($categoryFilter, $priceRange),
                    ),
                ),
            ),
            '/products/{id}' => new Paths\path(
                get: new Paths\Operation(
                    responses: new Responses(
                        x200: new Responses\Response(
                            description: 'Product details',
                            content: new RequestBodies\Contents(...[
                                'application/json' => new RequestBodies\Content(
                                    schema: $product,
                                ),
                            ]),
                        ),
                        x404: $notFound,
                        default: $generalError,
                    ),
                    summary: 'Get a specific product',
                    id: 'getProduct',
                    parameters: new Parameters\Parameters(
                        paths: new Parameters\Path\Paths(
                            id: new Parameters\Path\SchemaParameter(
                                schema: new Schemas\Integer\Schema(
                                    format: Schemas\Integer\Format::Int64,
                                ),
                                description: 'ID of the product',
                            ),
                        ),
                    ),
                ),
            ),
            '/categories' => new Paths\Path(
                get: new Paths\Operation(
                    responses: new Responses(
                        x200: new Responses\Response(
                            description: 'A list of categories',
                            content: new RequestBodies\Contents(...[
                                'application/json' => new RequestBodies\Content(
                                    schema: new Schemas\Array\Schema(
                                        items: $category,
                                    ),
                                ),
                            ]),
                        ),
                        default: $generalError,
                    ),
                    summary: 'List all categories',
                    id: 'listCategories',
                ),
            ),
        ]),
        components: new Components(
            schemas: new Schemas\Untyped\Schemas(
                Product: $product,
                Category: $category,
                Error: $error,
            ),
            responses: new Responses(
                NotFound: $notFound,
                GeneralError: $generalError,
            ),
            parameters: new Parameters(
                categoryFilter: new Parameters\Parameter(
                    name: 'category',
                    parameter: $categoryFilter,
                ),
                priceRange: new Parameters\Parameter(
                    name: 'priceRange',
                    parameter: $priceRange,
                ),
            ),
        ),
        servers: new Servers(
            new Servers\Server(
                url: 'https://api.advancedexample.com/v1',
                description: 'Main server',
            ),
        ),
    ),
];