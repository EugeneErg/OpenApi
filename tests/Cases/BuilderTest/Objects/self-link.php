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
 * Тело обёрнуто в замыкание: require выполняет файл в области видимости вызывающего,
 * а отложенные ссылки связываются через use (&$var) и подхватили бы чужие переменные.
 *
 * @return array<string, Openapi>
 */
return (static function (): array {
    // Пагинация: «следующая страница» — это та же операция, поэтому ссылка на неё
    // откладывается, как и у рекурсивных схем.
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

    // Операция без operationId: на неё ссылаются указателем на место в paths.
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
