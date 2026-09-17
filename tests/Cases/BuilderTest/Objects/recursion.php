<?php

declare(strict_types = 1);

use EugeneErg\OpenApi\Components;
use EugeneErg\OpenApi\Components\Schemas;
use EugeneErg\OpenApi\Components\Schemas\Abstract\DeferredSchema;
use EugeneErg\OpenApi\Info;
use EugeneErg\OpenApi\Openapi;

/**
 * The body is wrapped in a closure: require runs the file in the caller's scope, and the
 * deferred references are bound through use (&$var), which would pick up somebody else's
 * variables.
 *
 * @return array<string, Openapi>
 */
return (static function (): array {
    // The schema refers to itself: an object cannot be passed to its own constructor, so
    // the reference is deferred by a closure that binds by reference.
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

    // Mutual recursion between two schemas.
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
