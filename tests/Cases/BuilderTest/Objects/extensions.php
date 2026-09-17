<?php

declare(strict_types = 1);

use EugeneErg\OpenApi\Components;
use EugeneErg\OpenApi\Components\Responses;
use EugeneErg\OpenApi\Components\Schemas;
use EugeneErg\OpenApi\Components\SecuritySchemes;
use EugeneErg\OpenApi\Extensions;
use EugeneErg\OpenApi\Info;
use EugeneErg\OpenApi\Openapi;
use EugeneErg\OpenApi\Paths;
use EugeneErg\OpenApi\Servers;

// Имя пишется без префикса: x- добавляется при сборке.
// Значение — любое значение JSON, объекты и списки через OpenapiObject и OpenapiArray.
$pii = new Extensions(...['twilio' => new Schemas\Object\OpenapiObject(
    pii: new Schemas\Object\OpenapiObject(handling: 'standard', deleteSla: 30),
)]);

$name = new Schemas\String\Schema(extensions: $pii);

$openapi = new Openapi(
    info: new Info(
        title: 'Extensions',
        version: '1.0.0',
        contact: new Info\Contact(name: 'API team', extensions: new Extensions(slack: '#api')),
        license: new Info\License(name: 'MIT', extensions: new Extensions(spdx: true)),
        extensions: new Extensions(logo: new Schemas\Object\OpenapiObject(url: 'https://example.com/logo.png')),
    ),
    components: new Components(
        schemas: new Schemas\Untyped\Schemas(Name: $name),
        securitySchemes: new SecuritySchemes(
            apiKey: new SecuritySchemes\ApiKeySecurity\Scheme(
                name: 'X-Api-Key',
                in: SecuritySchemes\ApiKeySecurity\In::Header,
                extensions: new Extensions(internal: true),
            ),
        ),
        extensions: new Extensions(generated: true),
    ),
    paths: Paths::fromArray(
        [
            '/users' => new Paths\Path(
                get: new Paths\Operation(
                    responses: Responses::fromArray(
                        ['200' => new Responses\Response(
                            description: 'OK',
                            extensions: new Extensions(cache: 60),
                        )],
                        new Extensions(...['error-codes' => new Schemas\Array\OpenapiArray(400, 500)]),
                    ),
                    id: 'listUsers',
                    extensions: new Extensions(...['curl-samples' => new Schemas\Array\OpenapiArray('curl …')]),
                ),
                extensions: new Extensions(owner: 'platform'),
            ),
        ],
        new Extensions(section: 'users'),
    ),
    servers: new Servers(
        new Servers\Server(
            url: 'https://{region}.example.com',
            variables: new Servers\Variables(
                region: new Servers\Variable(default: 'eu', extensions: new Extensions(dns: 'geo')),
            ),
            extensions: new Extensions(internal: false),
        ),
    ),
    extensions: new Extensions(...['spec-filename' => 'extensions.json']),
);

return ['extensions.json' => $openapi];
