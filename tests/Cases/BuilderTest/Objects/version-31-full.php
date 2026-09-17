<?php

declare(strict_types = 1);

use EugeneErg\OpenApi\Components;
use EugeneErg\OpenApi\Components\Responses;
use EugeneErg\OpenApi\Components\Schemas;
use EugeneErg\OpenApi\Components\SecuritySchemes;
use EugeneErg\OpenApi\Components\SecuritySchemes\MutualTlsSecurityScheme;
use EugeneErg\OpenApi\Info;
use EugeneErg\OpenApi\Openapi;
use EugeneErg\OpenApi\PathItems;
use EugeneErg\OpenApi\Paths;
use EugeneErg\OpenApi\Version;

// One and the same Path Item lies in components and is reused, so in paths and in
// webhooks a $ref stands in its place.
$ping = new Paths\Path(
    get: new Paths\Operation(
        responses: new Responses(x200: new Responses\Response(description: 'Pong.')),
        id: 'ping',
    ),
    summary: 'Health check',
    description: 'Answers while the service is alive.',
);

$openapi = new Openapi(
    info: new Info(
        title: 'Full 3.1 API',
        version: '1.0.0',
        summary: 'A short summary, available in 3.1 only.',
        license: new Info\License(name: 'MIT', identifier: 'MIT'),
    ),
    components: new Components(
        schemas: new Schemas\Untyped\Schemas(
            Nickname: new Schemas\String\Schema(nullable: true, format: Schemas\String\Format::IdnEmail),
            Delay: new Schemas\Number\Schema(minimum: 0.0, exclusiveMinimum: true),
        ),
        securitySchemes: new SecuritySchemes(
            mtls: new MutualTlsSecurityScheme(description: 'A client certificate.'),
        ),
        pathItems: new PathItems(ping: $ping),
    ),
    paths: new Paths(...['/ping' => $ping]),
    version: Version::V311,
    jsonSchemaDialect: 'https://json-schema.org/draft/2020-12/schema',
    webhooks: new PathItems(healthPinged: $ping),
);

return ['full31.json' => $openapi];
