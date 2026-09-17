<?php

declare(strict_types = 1);

use EugeneErg\OpenApi\Components;
use EugeneErg\OpenApi\Components\Parameters;
use EugeneErg\OpenApi\Components\Responses;
use EugeneErg\OpenApi\Components\Schemas;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Discriminator;
use EugeneErg\OpenApi\ExternalDocs;
use EugeneErg\OpenApi\Info;
use EugeneErg\OpenApi\Openapi;
use EugeneErg\OpenApi\Paths;

$petType = new Schemas\Object\Property(
    schema: new Schemas\String\Schema(),
    required: true,
);

$dog = new Schemas\Object\Schema(
    properties: new Schemas\Object\Properties(
        petType: $petType,
        packSize: new Schemas\Object\Property(
            schema: new Schemas\Integer\Schema(format: Schemas\Integer\Format::Int32),
            required: true,
        ),
    ),
);

$cat = new Schemas\Object\Schema(
    properties: new Schemas\Object\Properties(
        petType: $petType,
        indoor: new Schemas\Object\Property(schema: new Schemas\Boolean\Schema()),
    ),
);

$pet = new Schemas\Untyped\Schema(
    description: 'Any pet.',
    oneOf: new Schemas\Untyped\Schemas($dog, $cat),
    discriminator: new Discriminator(
        propertyName: 'petType',
        mapping: new Schemas\Untyped\Schemas(dog: $dog, cat: $cat),
    ),
);

// deprecated + externalDocs + additionalProperties as a schema
$legacyBag = new Schemas\Object\Schema(
    properties: new Schemas\Object\Properties(
        id: new Schemas\Object\Property(schema: new Schemas\String\Schema(), required: true),
    ),
    description: 'Free-form legacy payload.',
    deprecated: true,
    externalDocs: new ExternalDocs(url: 'https://example.com/legacy', description: null),
    additionalProperties: new Schemas\String\Schema(),
);

$openapi = new Openapi(
    info: new Info(title: 'Discriminator API', version: '1.0.0'),
    components: new Components(
        schemas: new Schemas\Untyped\Schemas(
            Dog: $dog,
            Cat: $cat,
            Pet: $pet,
            LegacyBag: $legacyBag,
        ),
    ),
    paths: new Paths(...[
        '/pets/{id}' => new Paths\Path(
            get: new Paths\Operation(
                responses: new Responses(
                    x200: new Responses\Response(
                        description: 'A pet.',
                        content: new Components\RequestBodies\Contents(...[
                            'application/json' => new Components\RequestBodies\Content(schema: $pet),
                        ]),
                    ),
                ),
                id: 'getPet',
                parameters: new Parameters\Parameters(
                    paths: new Parameters\Path\Paths(
                        id: new Parameters\Path\SchemaParameter(
                            schema: new Schemas\String\Schema(),
                        ),
                    ),
                    queries: new Parameters\Query\Queries(
                        legacy: new Parameters\Query\SchemaParameter(
                            schema: new Schemas\Boolean\Schema(),
                            description: 'Use the legacy representation.',
                            deprecated: true,
                        ),
                    ),
                ),
            ),
        ),
    ]),
);

return ['discriminator.json' => $openapi];
