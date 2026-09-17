<?php

declare(strict_types = 1);

use EugeneErg\OpenApi\Components;
use EugeneErg\OpenApi\Components\Examples;
use EugeneErg\OpenApi\Components\Examples\Example;
use EugeneErg\OpenApi\Components\RequestBodies;
use EugeneErg\OpenApi\Components\Responses;
use EugeneErg\OpenApi\Components\Schemas;
use EugeneErg\OpenApi\Info;
use EugeneErg\OpenApi\Openapi;
use EugeneErg\OpenApi\PathItems;
use EugeneErg\OpenApi\Paths;
use EugeneErg\OpenApi\Reference;
use EugeneErg\OpenApi\Version;

$notFound = new Responses\Response(description: 'Resource not found.');
$sample = new Example(value: new Schemas\String\Value('abc-123'), summary: 'Component level summary.');

$ping = new Paths\Path(
    get: new Paths\Operation(
        responses: new Responses(x200: new Responses\Response(description: 'Pong.')),
        id: 'ping',
    ),
);

$pageQuery = new Components\Parameters\Query\SchemaParameter(
    schema: new Schemas\Integer\Schema(),
    description: 'Component level page.',
);

$upload = new RequestBodies\RequestBody(
    content: new RequestBodies\Contents(...[
        'application/json' => new RequestBodies\Content(schema: new Schemas\String\Schema()),
    ]),
    description: 'Component level body.',
);

// Callback Object — карта runtime-выражений; как компонент он адресуется ссылкой
$onEvent = PathItems::fromArray([
    '{$request.body#/callbackUrl}' => new Paths\Path(
        post: new Paths\Operation(
            responses: new Responses(x204: new Responses\Response(description: 'Accepted.')),
        ),
    ),
]);

$openapi = new Openapi(
    info: new Info(title: 'References', version: '1.0.0'),
    components: new Components(
        responses: new Responses(NotFound: $notFound),
        examples: new Examples(Sample: $sample),
        pathItems: new PathItems(Ping: $ping),
        requestBodies: new RequestBodies(Upload: $upload),
        parameters: new Components\Parameters(page: new Components\Parameters\Parameter(name: 'page', parameter: $pageQuery)),
        callbacks: new Components\Callbacks(onEvent: $onEvent),
    ),
    paths: new Paths(...[
        // тот же объект: без Reference — голый $ref, с Reference — с переопределением
        '/users' => new Paths\Path(
            get: new Paths\Operation(
                responses: new Responses(
                    x200: new Responses\Response(
                        description: 'A user.',
                        content: new RequestBodies\Contents(...[
                            'application/json' => new RequestBodies\Content(
                                schema: new Schemas\String\Schema(),
                                examples: new Examples(
                                    plain: $sample,
                                    annotated: new Reference($sample, summary: 'Overridden at use site.'),
                                ),
                            ),
                        ]),
                    ),
                    x404: new Reference($notFound, description: 'User not found.'),
                    x500: $notFound,
                ),
                id: 'getUser',
            ),
            post: new Paths\Operation(
                responses: new Responses(x201: new Responses\Response(description: 'Created.')),
                id: 'createUser',
                requestBody: new Reference($upload, description: 'A user to create.'),
                callbacks: new Components\Callbacks(onCreated: $onEvent),
                parameters: new Components\Parameters\Parameters(
                    queries: new Components\Parameters\Query\Queries($pageQuery),
                ),
            ),
            put: new Paths\Operation(
                responses: new Responses(x200: new Responses\Response(description: 'Replaced.')),
                id: 'replaceUser',
                requestBody: $upload,
                // имя параметра берётся из объявления компонента, поэтому его здесь не передают
                parameters: new Components\Parameters\Parameters(
                    queries: new Components\Parameters\Query\Queries(
                        new Reference($pageQuery, description: 'Overridden at use site.'),
                    ),
                ),
            ),
        ),
        '/health' => new Reference($ping, summary: 'Liveness probe'),
    ]),
    version: Version::V311,
);

return ['references.json' => $openapi];
