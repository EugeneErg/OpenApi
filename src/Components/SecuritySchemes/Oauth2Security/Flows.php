<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security;

use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows\AbstractFlow;
use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows\AuthorizationCodeFlow;
use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows\ClientCredentialsFlow;
use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows\ImplicitFlow;
use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows\PasswordFlow;
use EugeneErg\OpenApi\Extensions;
use stdClass;

final readonly class Flows
{
    /** @var array<string, AbstractFlow> */
    public array $items;

    public Extensions $extensions;

    private function __construct(
        public ?ImplicitFlow $implicit = null,
        public ?PasswordFlow $password = null,
        public ?ClientCredentialsFlow $clientCredentials = null,
        public ?AuthorizationCodeFlow $authorizationCode = null,
        ?Extensions $extensions = null,
    ) {
        $this->extensions = $extensions ?? new Extensions();
        $this->items = array_filter([
            'implicit' => $implicit,
            'password' => $password,
            'clientCredentials' => $clientCredentials,
            'authorizationCode' => $authorizationCode,
        ], static fn (?AbstractFlow $flow) => $flow !== null);
    }

    public function toObject(): stdClass
    {
        $result = [];

        foreach ($this->items as $name => $flow) {
            $result[$name] = $flow->toObject();
        }

        return (object) $this->extensions->appendTo($result);
    }

    public static function createImplicit(
        ImplicitFlow $implicit,
        ?PasswordFlow $password,
        ?ClientCredentialsFlow $clientCredentials,
        ?AuthorizationCodeFlow $authorizationCode,
        ?Extensions $extensions = null,
    ): self {
        return new self(
            implicit: $implicit,
            password: $password,
            clientCredentials: $clientCredentials,
            authorizationCode: $authorizationCode,
            extensions: $extensions,
        );
    }

    public static function createPassword(
        PasswordFlow $password,
        ?ClientCredentialsFlow $clientCredentials,
        ?AuthorizationCodeFlow $authorizationCode,
        ?Extensions $extensions = null,
    ): self {
        return new self(
            password: $password,
            clientCredentials: $clientCredentials,
            authorizationCode: $authorizationCode,
            extensions: $extensions,
        );
    }

    public static function createClientCredentials(
        ClientCredentialsFlow $clientCredentials,
        ?AuthorizationCodeFlow $authorizationCode,
        ?Extensions $extensions = null,
    ): self {
        return new self(
            clientCredentials: $clientCredentials,
            authorizationCode: $authorizationCode,
            extensions: $extensions,
        );
    }

    public static function createAuthorizationCode(
        AuthorizationCodeFlow $authorizationCode,
        ?Extensions $extensions = null,
    ): self {
        return new self(authorizationCode: $authorizationCode, extensions: $extensions);
    }
}
