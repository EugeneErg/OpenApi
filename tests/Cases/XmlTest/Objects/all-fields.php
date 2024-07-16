<?php

declare(strict_types = 1);

use EugeneErg\OpenApi\Components\Schemas\Abstract\Xml;

return new Xml(
    name: 'name1',
    namespace: 'namespace1',
    prefix: 'prefix1',
    attribute: true,
    wrapped: true,
);
