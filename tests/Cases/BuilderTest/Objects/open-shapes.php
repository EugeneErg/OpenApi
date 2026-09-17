<?php

declare(strict_types = 1);

use EugeneErg\OpenApi\Components;
use EugeneErg\OpenApi\Components\RequestBodies;
use EugeneErg\OpenApi\Components\Responses;
use EugeneErg\OpenApi\Components\Schemas;
use EugeneErg\OpenApi\Info;
use EugeneErg\OpenApi\Openapi;
use EugeneErg\OpenApi\Paths;

// Everything gathered here the specification allows and the package once forbade.
// Found while reading real specifications from the OAI repository.

// format is an open value rather than the known enum alone
$uriRef = new Schemas\String\Schema(format: 'uriref');

// the schema describes a shape without declaring type
$subscription = new Schemas\Object\Schema(
    properties: new Schemas\Object\Properties(
        subscriptionId: new Schemas\Object\Property(schema: new Schemas\String\Schema(), required: true),
    ),
    description: 'subscription information',
    declareType: false,
);

// a check lives without a declared type as well: it does not stand in the way of a value of another type
$longEnough = new Schemas\String\Schema(minLength: 3, declareType: false);
$even = new Schemas\Number\Schema(multipleOf: 2, declareType: false);
// in 3.0 items is required only when type: array is declared
$unique = new Schemas\Array\Schema(uniqueItems: true, declareType: false);

// the example value holds a nested list of objects
$versions = new Schemas\Object\Value(new Schemas\Object\OpenapiObject(
    versions: new Schemas\Untyped\Values(
        new Schemas\Object\OpenapiObject(id: 'v2.0', status: 'CURRENT'),
        new Schemas\Object\OpenapiObject(id: 'v3.0', status: 'EXPERIMENTAL'),
    ),
));

$openapi = new Openapi(
    info: new Info(title: 'Open shapes', version: '1.0.0'),
    components: new Components(
        schemas: new Schemas\Untyped\Schemas(
            UriRef: $uriRef,
            Subscription: $subscription,
            LongEnough: $longEnough,
            Even: $even,
            Unique: $unique,
        ),
    ),
    paths: new Paths(...[
        '/streams' => new Paths\Path(
            post: new Paths\Operation(
                responses: new Responses(
                    x201: new Responses\Response(
                        description: 'subscription created',
                        content: new RequestBodies\Contents(...[
                            'application/json' => new RequestBodies\Content(schema: $subscription),
                        ]),
                    ),
                    // a media type without a schema: the specification does not require one
                    x200: new Responses\Response(
                        description: 'versions',
                        content: new RequestBodies\Contents(...[
                            'application/json' => new RequestBodies\Content(example: $versions),
                        ]),
                    ),
                ),
                id: 'createStream',
            ),
        ),
    ]),
);

return ['open-shapes.json' => $openapi];
