<?php

declare(strict_types = 1);

use EugeneErg\OpenApi\Components;
use EugeneErg\OpenApi\Components\Schemas;
use EugeneErg\OpenApi\Info;
use EugeneErg\OpenApi\Openapi;

/**
 * У каждого документа своя секция components.schemas. Ни одна из них не должна
 * превратиться в $ref на соседний файл: одинаковых объектов здесь нет.
 */
$first = new Openapi(
    info: new Info(title: 'First', version: '1.0.0'),
    components: new Components(
        schemas: new Schemas\Untyped\Schemas(
            Alpha: new Schemas\String\Schema(),
        ),
    ),
);

$second = new Openapi(
    info: new Info(title: 'Second', version: '1.0.0'),
    components: new Components(
        schemas: new Schemas\Untyped\Schemas(
            Beta: new Schemas\Integer\Schema(),
        ),
    ),
);

return [
    'first.json' => $first,
    'second.json' => $second,
];
