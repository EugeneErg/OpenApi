<?php

declare(strict_types = 1);

use EugeneErg\OpenApi\Components;
use EugeneErg\OpenApi\Components\Schemas;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Resource;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Vocabularies;
use EugeneErg\OpenApi\Info;
use EugeneErg\OpenApi\Openapi;
use EugeneErg\OpenApi\Version;

// A schema inside $defs, nested two levels deep. A reference to it is built the same way
// as one to an ordinary component: the object itself is passed.
$street = new Schemas\String\Schema(minLength: 1);

$address = new Schemas\Object\Schema(
    properties: new Schemas\Object\Properties(
        street: new Schemas\Object\Property(schema: $street, required: true),
    ),
    resource: new Resource(defs: new Schemas\Untyped\Schemas(Street: $street)),
);

$user = new Schemas\Object\Schema(
    properties: new Schemas\Object\Properties(
        home: new Schemas\Object\Property(schema: $address),
        work: new Schemas\Object\Property(schema: $address),
        road: new Schemas\Object\Property(schema: $street),
    ),
    resource: new Resource(defs: new Schemas\Untyped\Schemas(Address: $address)),
);

// Recursion through a dynamic anchor: $dynamicRef is handed the object, and the builder
// writes the "#node" string itself.
$node = new Schemas\Object\Schema(
    properties: new Schemas\Object\Properties(
        value: new Schemas\Object\Property(schema: new Schemas\String\Schema()),
    ),
    // the resource: the identifier, the dialect, the vocabulary and the anchors are declared together
    resource: new Resource(
        id: 'https://example.com/schemas/node',
        schema: 'https://json-schema.org/draft/2020-12/schema',
        vocabulary: new Vocabularies(...[
            'https://json-schema.org/draft/2020-12/vocab/core' => true,
            'https://json-schema.org/draft/2020-12/vocab/validation' => false,
        ]),
        anchor: 'root',
        dynamicAnchor: 'node',
    ),
);

$tree = new Schemas\Object\Schema(
    properties: new Schemas\Object\Properties(
        children: new Schemas\Object\Property(
            schema: new Schemas\Array\Schema(
                items: new Schemas\Untyped\Schema(resource: new Resource(dynamicRef: $node)),
            ),
        ),
    ),
);

$openapi = new Openapi(
    info: new Info(title: 'JSON Schema core', version: '1.0.0'),
    components: new Components(
        schemas: new Schemas\Untyped\Schemas(
            User: $user,
            Node: $node,
            Tree: $tree,
        ),
    ),
    version: Version::V311,
);

return ['json-schema-core.json' => $openapi];
