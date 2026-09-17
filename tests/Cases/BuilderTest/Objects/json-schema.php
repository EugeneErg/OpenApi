<?php

declare(strict_types = 1);

use EugeneErg\OpenApi\Components;
use EugeneErg\OpenApi\Components\Schemas;
use EugeneErg\OpenApi\Components\Schemas\Object\DependentRequired;
use EugeneErg\OpenApi\Components\Schemas\Object\PatternProperties;
use EugeneErg\OpenApi\Info;
use EugeneErg\OpenApi\Openapi;
use EugeneErg\OpenApi\Version;

$string = new Schemas\String\Schema();

// if / then / else
$card = new Schemas\Object\Schema(
    properties: new Schemas\Object\Properties(
        kind: new Schemas\Object\Property(schema: $string, required: true),
    ),
    if: new Schemas\Object\Schema(
        properties: new Schemas\Object\Properties(
            kind: new Schemas\Object\Property(schema: new Schemas\String\EnumSchema(new Schemas\String\Strings('credit'))),
        ),
    ),
    then: new Schemas\Object\Schema(
        properties: new Schemas\Object\Properties(
            limit: new Schemas\Object\Property(schema: new Schemas\Integer\Schema(), required: true),
        ),
    ),
    else: new Schemas\Object\Schema(
        properties: new Schemas\Object\Properties(
            balance: new Schemas\Object\Property(schema: new Schemas\Integer\Schema(), required: true),
        ),
    ),
);

// patternProperties, propertyNames, dependentRequired, dependentSchemas, unevaluatedProperties
$extensible = new Schemas\Object\Schema(
    properties: new Schemas\Object\Properties(
        id: new Schemas\Object\Property(schema: $string, required: true),
    ),
    comment: 'Свободные поля разрешены только с префиксом x-.',
    patternProperties: new PatternProperties(...['^x-' => $string]),
    propertyNames: new Schemas\String\Schema(pattern: '^[a-z][a-zA-Z0-9-]*$'),
    dependentRequired: new DependentRequired(
        billingAddress: new Schemas\String\Strings('creditCard'),
    ),
    dependentSchemas: new Schemas\Untyped\Schemas(
        creditCard: new Schemas\Object\Schema(
            properties: new Schemas\Object\Properties(
                cvv: new Schemas\Object\Property(schema: $string, required: true),
            ),
        ),
    ),
    unevaluatedProperties: false,
);

// prefixItems, contains, min/maxContains, unevaluatedItems
$tuple = new Schemas\Array\Schema(
    items: $string,
    prefixItems: new Schemas\Untyped\Schemas(
        new Schemas\Number\Schema(),
        new Schemas\Number\Schema(),
    ),
    contains: new Schemas\String\Schema(minLength: 1),
    minContains: 1,
    maxContains: 3,
    unevaluatedItems: false,
);

// content*
$attachment = new Schemas\String\Schema(
    contentEncoding: 'base64',
    contentMediaType: 'application/json',
    contentSchema: new Schemas\Object\Schema(
        properties: new Schemas\Object\Properties(
            name: new Schemas\Object\Property(schema: $string, required: true),
        ),
    ),
);

// examples у обычной схемы; единственное значение перечисления в 3.1 печатается как const
$status = new Schemas\String\Schema(
    examples: new Schemas\Untyped\Values('active'),
);
$active = new Schemas\String\EnumSchema(new Schemas\String\Strings('active'));

$openapi = new Openapi(
    info: new Info(title: 'JSON Schema 2020-12', version: '1.0.0'),
    components: new Components(
        schemas: new Schemas\Untyped\Schemas(
            Card: $card,
            Extensible: $extensible,
            Tuple: $tuple,
            Attachment: $attachment,
            Status: $status,
            Active: $active,
        ),
    ),
    version: Version::V311,
);

return ['json-schema.json' => $openapi];
