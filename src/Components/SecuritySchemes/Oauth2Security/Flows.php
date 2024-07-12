<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security;

use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows\AbstractFlow;
use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows\AuthorizationCodeFlow;
use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows\ClientCredentialsFlow;
use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows\ImplicitFlow;
use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows\PasswordFlow;

final readonly class Flows
{
    /** @var array<string, AbstractFlow> */
    public array $items;

    private function __construct(
        public ?ImplicitFlow $implicit = null,
        public ?PasswordFlow $password = null,
        public ?ClientCredentialsFlow $clientCredentials = null,
        public ?AuthorizationCodeFlow $authorizationCode = null,
    ) {
        $this->items = array_filter([
            'implicit' => $implicit,
            'password' => $password,
            'clientCredentials' => $clientCredentials,
            'authorizationCode' => $authorizationCode,
        ], static fn (?AbstractFlow $flow) => $flow !== null);
    }

    public static function createImplicit(
        ImplicitFlow $implicit,
        ?PasswordFlow $password,
        ?ClientCredentialsFlow $clientCredentials,
        ?AuthorizationCodeFlow $authorizationCode,
    ): self {
        return new self(
            implicit: $implicit,
            password: $password,
            clientCredentials: $clientCredentials,
            authorizationCode: $authorizationCode,
        );
    }

    public static function createPassword(
        PasswordFlow $password,
        ?ClientCredentialsFlow $clientCredentials,
        ?AuthorizationCodeFlow $authorizationCode,
    ): self {
        return new self(password: $password, clientCredentials: $clientCredentials, authorizationCode: $authorizationCode);
    }

    public static function createClientCredentials(
        ClientCredentialsFlow $clientCredentials,
        ?AuthorizationCodeFlow $authorizationCode
    ): self {
        return new self(clientCredentials: $clientCredentials, authorizationCode: $authorizationCode);
    }

    public static function createAuthorizationCode(AuthorizationCodeFlow $authorizationCode): self
    {
        return new self(authorizationCode: $authorizationCode);
    }
}
