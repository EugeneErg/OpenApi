<?php

declare(strict_types = 1);

use EugeneErg\OpenApi\Components;
use EugeneErg\OpenApi\Components\Examples;
use EugeneErg\OpenApi\Components\Examples\Example;
use EugeneErg\OpenApi\Components\Parameters;
use EugeneErg\OpenApi\Components\RequestBodies;
use EugeneErg\OpenApi\Components\Responses;
use EugeneErg\OpenApi\Components\Schemas;
use EugeneErg\OpenApi\Info;
use EugeneErg\OpenApi\Openapi;
use EugeneErg\OpenApi\Paths;

$active = new Example(
    value: new Schemas\String\Value('active'),
    summary: 'Active record',
);

$external = new Example(
    externalValue: 'https://example.com/examples/archived.json',
    description: 'Stored outside the document.',
);

// "active" occurs as the value of an example, as a member of an enum and as a default.
// Only the example should become a reference; the other two are ordinary strings.
$status = new Schemas\String\EnumSchema(
    enums: new Schemas\String\Strings('active', 'archived'),
);

$mode = new Schemas\String\Schema(default: new Schemas\String\Value('active'));

$openapi = new Openapi(
    info: new Info(title: 'Examples API', version: '1.0.0'),
    components: new Components(
        examples: new Examples(Active: $active, Archived: $external),
        schemas: new Schemas\Untyped\Schemas(Status: $status, Mode: $mode),
    ),
    paths: new Paths(...[
        '/records' => new Paths\Path(
            get: new Paths\Operation(
                responses: new Responses(
                    x200: new Responses\Response(
                        description: 'A record.',
                        content: new RequestBodies\Contents(...[
                            'application/json' => new RequestBodies\Content(
                                schema: $status,
                                examples: new Examples(active: $active, archived: $external),
                            ),
                        ]),
                    ),
                ),
                id: 'getRecord',
                parameters: new Parameters\Parameters(
                    queries: new Parameters\Query\Queries(
                        status: new Parameters\Query\SchemaParameter(
                            schema: $status,
                            example: new Schemas\String\Value('archived'),
                        ),
                        mode: new Parameters\Query\SchemaParameter(
                            schema: $mode,
                            examples: new Examples(active: $active),
                        ),
                    ),
                ),
            ),
        ),
    ]),
);

return ['examples.json' => $openapi];
