<?php

declare(strict_types = 1);

use EugeneErg\OpenApi\Components;
use EugeneErg\OpenApi\Components\Schemas;
use EugeneErg\OpenApi\Components\Schemas\Abstract\DeferredSchema;
use EugeneErg\OpenApi\Info;
use EugeneErg\OpenApi\Openapi;

/**
 * Тело обёрнуто в замыкание: require выполняет файл в области видимости вызывающего,
 * а отложенные ссылки связываются через use (&$var) и подхватили бы чужие переменные.
 *
 * @return array<string, Openapi>
 */
return (static function (): array {
    // Схема ссылается на саму себя: объект нельзя передать в собственный конструктор,
    // поэтому ссылка откладывается замыканием по ссылке.
    $node = new Schemas\Object\Schema(
        properties: new Schemas\Object\Properties(
            value: new Schemas\Object\Property(schema: new Schemas\String\Schema(), required: true),
            children: new Schemas\Object\Property(
                schema: new Schemas\Array\Schema(
                    items: new DeferredSchema(static function () use (&$node): Schemas\Object\Schema {
                        return $node;
                    }),
                ),
            ),
        ),
    );

    // Взаимная рекурсия между двумя схемами.
    $folder = new Schemas\Object\Schema(
        properties: new Schemas\Object\Properties(
            files: new Schemas\Object\Property(
                schema: new Schemas\Array\Schema(
                    items: new DeferredSchema(static function () use (&$file): Schemas\Object\Schema {
                        return $file;
                    }),
                ),
            ),
        ),
    );

    $file = new Schemas\Object\Schema(
        properties: new Schemas\Object\Properties(
            parent: new Schemas\Object\Property(
                schema: new DeferredSchema(static function () use (&$folder): Schemas\Object\Schema {
                    return $folder;
                }),
            ),
        ),
    );

    $openapi = new Openapi(
        info: new Info(title: 'Recursive schemas', version: '1.0.0'),
        components: new Components(
            schemas: new Schemas\Untyped\Schemas(
                Node: $node,
                Folder: $folder,
                File: $file,
            ),
        ),
    );

    return ['recursion.json' => $openapi];
})();
