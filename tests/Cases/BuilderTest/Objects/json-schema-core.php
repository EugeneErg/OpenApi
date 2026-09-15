<?php

declare(strict_types = 1);

use EugeneErg\OpenApi\Components;
use EugeneErg\OpenApi\Components\Schemas;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Vocabularies;
use EugeneErg\OpenApi\Info;
use EugeneErg\OpenApi\Openapi;
use EugeneErg\OpenApi\Version;

// Схема внутри $defs, вложенная на два уровня. Ссылка на неё строится
// тем же способом, что и на обычный компонент: передаётся сам объект.
$street = new Schemas\String\Schema(minLength: 1);

$address = new Schemas\Object\Schema(
    properties: new Schemas\Object\Properties(
        street: new Schemas\Object\Property(schema: $street, required: true),
    ),
    defs: new Schemas\Untyped\Schemas(Street: $street),
);

$user = new Schemas\Object\Schema(
    properties: new Schemas\Object\Properties(
        home: new Schemas\Object\Property(schema: $address),
        work: new Schemas\Object\Property(schema: $address),
        road: new Schemas\Object\Property(schema: $street),
    ),
    defs: new Schemas\Untyped\Schemas(Address: $address),
);

// Рекурсия через динамический якорь: $dynamicRef получает объект,
// а строку «#node» подставляет сборщик.
$node = new Schemas\Object\Schema(
    properties: new Schemas\Object\Properties(
        value: new Schemas\Object\Property(schema: new Schemas\String\Schema()),
    ),
    id: 'https://example.com/schemas/node',
    anchor: 'root',
    dynamicAnchor: 'node',
    vocabulary: new Vocabularies(...[
        'https://json-schema.org/draft/2020-12/vocab/core' => true,
        'https://json-schema.org/draft/2020-12/vocab/validation' => false,
    ]),
);

$tree = new Schemas\Object\Schema(
    properties: new Schemas\Object\Properties(
        children: new Schemas\Object\Property(
            schema: new Schemas\Array\Schema(
                items: new Schemas\Untyped\Schema(dynamicRef: $node),
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
