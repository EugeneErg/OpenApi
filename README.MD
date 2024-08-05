# eugene-erg/open-api

`eugene-erg/open-api` is a PHP package designed to facilitate the creation of OpenAPI configurations in an object-oriented manner. This package allows developers to define and manage OpenAPI schemas, security schemes, and other components using a clean and expressive API.

## Features

- **Object-Oriented Configuration**: Define OpenAPI schemas, security schemes, and components using PHP classes and objects.
- **Rich Schema Support**: Create complex schemas for `integer`, `number`, `string`, `boolean`, `object`, and `untyped` data types.
- **Security Schemes**: Support for multiple security schemes including `API Key`, `Bearer`, and `OAuth2`.
- **Flexible Builders**: Use builder classes to create and manage schemas and security schemes.

## Advantages

- **Type Safety**: Leverage PHP’s strong typing for more robust and error-free code.
- **Readability**: The object-oriented approach makes configurations more readable and maintainable.
- **Extensibility**: Easily extend and customize components as needed.

## Comparison

| Feature                       | eugene-erg/open-api        | Other Popular Packages         |
|-------------------------------|----------------------------|--------------------------------|
| Object-Oriented API           | Yes                        | Varies (often procedural)      |
| Schema Definition             | Yes (detailed support)     | Limited or requires manual setup |
| Security Scheme Configuration | Yes (OAuth2, API Key, Bearer) | Varies, often less flexible    |
| Builder Classes               | Yes                        | Limited or non-existent        |

## Installation

To install the package, run the following command:

```bash
composer require eugene-erg/open-api
```

## Usage

Creating Schemas

```php
<?php

declare(strict_types = 1);

use EugeneErg\OpenApi\Builder;
use EugeneErg\OpenApi\Components;
use EugeneErg\OpenApi\Components\Parameters;
use EugeneErg\OpenApi\Components\RequestBodies;
use EugeneErg\OpenApi\Components\Responses;
use EugeneErg\OpenApi\Components\Schemas;
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

$openapi =new Openapi(
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
);

/** @var array<string<fileName>, stdClass<OpenapiConfig>> $results */
$results = (new Builder($openapi))->prepareToSave('path/directory');
```

If an entity object is used in components and where it is required, it will be converted to a $ref.
