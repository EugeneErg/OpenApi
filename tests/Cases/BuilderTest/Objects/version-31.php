<?php

declare(strict_types = 1);

use EugeneErg\OpenApi\Components;
use EugeneErg\OpenApi\Components\Responses;
use EugeneErg\OpenApi\Components\Schemas;
use EugeneErg\OpenApi\Info;
use EugeneErg\OpenApi\Openapi;
use EugeneErg\OpenApi\PathItems;
use EugeneErg\OpenApi\Paths;
use EugeneErg\OpenApi\Version;

$nickname = new Schemas\String\Schema(
    description: 'May be absent.',
    nullable: true,
);

$rating = new Schemas\Number\Schema(
    minimum: 0.0,
    maximum: 5.0,
    exclusiveMinimum: true,
);

$age = new Schemas\Integer\Schema(
    minimum: 0,
    maximum: 150,
    multipleOf: 1,
);

$openapi = new Openapi(
    info: new Info(title: 'Webhook API', version: '1.0.0'),
    components: new Components(
        schemas: new Schemas\Untyped\Schemas(
            Nickname: $nickname,
            Rating: $rating,
            Age: $age,
        ),
    ),
    version: Version::V310,
    webhooks: new PathItems(...[
        'userCreated' => new Paths\Path(
            post: new Paths\Operation(
                responses: new Responses(
                    x200: new Responses\Response(description: 'Webhook accepted.'),
                ),
                id: 'onUserCreated',
            ),
        ),
    ]),
);

return ['webhooks.json' => $openapi];
