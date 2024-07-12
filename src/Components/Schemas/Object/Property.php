<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Object;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchema;

final readonly class Property
{
    public function __construct(public AbstractSchema $schema, public bool $required = false)
    {
    }
}
