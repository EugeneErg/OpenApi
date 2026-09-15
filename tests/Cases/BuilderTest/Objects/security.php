<?php

declare(strict_types = 1);

use EugeneErg\OpenApi\Components;
use EugeneErg\OpenApi\Components\Responses;
use EugeneErg\OpenApi\Components\SecuritySchemes;
use EugeneErg\OpenApi\Components\SecuritySchemes\ApiKeySecurity;
use EugeneErg\OpenApi\Components\SecuritySchemes\BasicHttpSecurityScheme;
use EugeneErg\OpenApi\Components\SecuritySchemes\BearerHttpSecurityScheme;
use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security;
use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows\AuthorizationCodeFlow;
use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows\ClientCredentialsFlow;
use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows\Scope;
use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows\Scopes;
use EugeneErg\OpenApi\Components\SecuritySchemes\OpenIdConnectSecurityScheme;
use EugeneErg\OpenApi\Info;
use EugeneErg\OpenApi\Openapi;
use EugeneErg\OpenApi\Paths;
use EugeneErg\OpenApi\Securities;

$readPets = new Scope('Read your pets');
$writePets = new Scope('Modify your pets');
$machine = new Scope('Machine-to-machine access');

$oauth = new Oauth2Security\Scheme(
    flows: Oauth2Security\Flows::createAuthorizationCode(
        new AuthorizationCodeFlow(
            authorizationUrl: 'https://example.com/oauth/authorize',
            tokenUrl: 'https://example.com/oauth/token',
            scopes: new Scopes(...[
                'read:pets' => $readPets,
                'write:pets' => $writePets,
            ]),
            refreshUrl: 'https://example.com/oauth/refresh',
        ),
    ),
    description: 'OAuth2 authorization code flow.',
);

$clientCredentials = new Oauth2Security\Scheme(
    flows: Oauth2Security\Flows::createClientCredentials(
        new ClientCredentialsFlow(
            tokenUrl: 'https://example.com/oauth/token',
            scopes: new Scopes(machine: $machine),
        ),
        null,
    ),
);

$apiKey = new ApiKeySecurity\Scheme(
    name: 'X-Api-Key',
    in: ApiKeySecurity\In::Header,
    description: 'Static key issued per integration.',
);

$basic = new BasicHttpSecurityScheme(description: 'Legacy basic auth.');
$bearer = new BearerHttpSecurityScheme(format: 'JWT');
$oidc = new OpenIdConnectSecurityScheme(
    openIdConnectUrl: 'https://example.com/.well-known/openid-configuration',
);

$openapi = new Openapi(
    info: new Info(
        title: 'Security API',
        version: '1.0.0',
        contact: new Info\Contact(name: 'API team', email: 'api@example.com'),
        license: new Info\License(name: 'MIT', url: 'https://opensource.org/licenses/MIT'),
    ),
    components: new Components(
        securitySchemes: new SecuritySchemes(
            oauth: $oauth,
            machineOauth: $clientCredentials,
            apiKey: $apiKey,
            basic: $basic,
            bearer: $bearer,
            oidc: $oidc,
        ),
    ),
    paths: new Paths(...[
        '/pets' => new Paths\Path(
            get: new Paths\Operation(
                responses: new Responses(
                    x200: new Responses\Response(description: 'A list of pets.'),
                    x4XX: new Responses\Response(description: 'Client error.'),
                ),
                id: 'listPets',
                security: new Securities(
                    new Securities\SecuritySchemes($readPets),
                    new Securities\SecuritySchemes($apiKey),
                ),
            ),
            post: new Paths\Operation(
                responses: new Responses(
                    x201: new Responses\Response(description: 'Created.'),
                ),
                id: 'createPet',
                deprecated: true,
                security: new Securities(
                    new Securities\SecuritySchemes($readPets, $writePets),
                ),
            ),
        ),
    ]),
    security: new Securities(
        new Securities\SecuritySchemes($bearer),
        new Securities\SecuritySchemes($machine),
    ),
);

return ['security.json' => $openapi];
