<?php

declare(strict_types = 1);

use EugeneErg\OpenApi\Components;
use EugeneErg\OpenApi\Info;
use EugeneErg\OpenApi\Openapi;
use EugeneErg\OpenApi\Version;

return ['enums-31.json' => new Openapi(
    info: new Info(title: 'Enums', version: '1.0.0'),
    components: new Components(schemas: require __DIR__ . '/../../../Fixtures/enum-schemas.php'),
    version: Version::V311,
)];
