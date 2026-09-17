<?php

declare(strict_types = 1);

use EugeneErg\OpenApi\Components;
use EugeneErg\OpenApi\Components\Links;
use EugeneErg\OpenApi\Components\Links\Link;
use EugeneErg\OpenApi\Components\Responses;
use EugeneErg\OpenApi\Components\Schemas;
use EugeneErg\OpenApi\Info;
use EugeneErg\OpenApi\Openapi;
use EugeneErg\OpenApi\Paths;
use EugeneErg\OpenApi\Paths\DeferredOperation;

/**
 * The body is wrapped in a closure: require runs the file in the caller's scope, and the
 * deferred references are bound through use (&$var), which would pick up somebody else's
 * variables.
 *
 * @return array<string, Openapi>
 */
return (static function (): array {
    // Pagination: the "next page" is the same operation, so the reference to it is
    // deferred, as with recursive schemas.
    $listUsers = new Paths\Operation(
        responses: new Responses(
            x200: new Responses\Response(
                description: 'A page of users',
                links: new Links(
                    next: new Link(
                        operation: new DeferredOperation(
                            static function () use (&$listUsers): Paths\Operation {
                                return $listUsers;
                            },
                        ),
                        parameters: new Link\Parameters(
                            page: Link\Parameter::expression('$response.body#/next'),
                        ),
                        description: 'The next page of the same listing.',
                    ),
                ),
            ),
        ),
        id: 'listUsers',
        parameters: new Components\Parameters\Parameters(
            queries: new Components\Parameters\Query\Queries(
                page: new Components\Parameters\Query\SchemaParameter(schema: new Schemas\Integer\Schema()),
            ),
        ),
    );

    // An operation without an operationId: it is referred to by a pointer into paths.
    $ping = new Paths\Operation(
        responses: new Responses(
            x200: new Responses\Response(
                description: 'Pong',
                links: new Links(
                    again: new Link(
                        operation: new DeferredOperation(
                            static function () use (&$ping): Paths\Operation {
                                return $ping;
                            },
                        ),
                    ),
                ),
            ),
        ),
    );

    return ['self-link.json' => new Openapi(
        info: new Info(title: 'Self-linking operations', version: '1.0.0'),
        paths: Paths::fromArray([
            '/users' => new Paths\Path(get: $listUsers),
            '/ping' => new Paths\Path(get: $ping),
        ]),
    )];
})();
