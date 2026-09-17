<?php

declare(strict_types = 1);

use EugeneErg\OpenApi\Components;
use EugeneErg\OpenApi\Components\RequestBodies;
use EugeneErg\OpenApi\Components\Responses;
use EugeneErg\OpenApi\Components\Schemas;
use EugeneErg\OpenApi\Info;
use EugeneErg\OpenApi\Openapi;
use EugeneErg\OpenApi\Paths;

// Всё, что здесь собрано, спецификация разрешает, а пакет когда-то запрещал.
// Нашлось при чтении настоящих спецификаций из репозитория OAI.

// format — открытое значение, а не только известный enum
$uriRef = new Schemas\String\Schema(format: 'uriref');

// схема описывает форму, не объявляя type
$subscription = new Schemas\Object\Schema(
    properties: new Schemas\Object\Properties(
        subscriptionId: new Schemas\Object\Property(schema: new Schemas\String\Schema(), required: true),
    ),
    description: 'subscription information',
    declareType: false,
);

// проверка живёт и без объявленного типа: значению другого типа она не помеха
$longEnough = new Schemas\String\Schema(minLength: 3, declareType: false);
$even = new Schemas\Number\Schema(multipleOf: 2, declareType: false);
// в 3.0 items обязателен только при объявленном type: array
$unique = new Schemas\Array\Schema(uniqueItems: true, declareType: false);

// пример-значение содержит вложенный список объектов
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
                    // media type без схемы: спецификация её не требует
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
