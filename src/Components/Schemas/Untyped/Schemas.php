<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Untyped;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchemas;

/**
 * @property AbstractSchema[] $items
 */
final readonly class Schemas extends AbstractSchemas
{
    public function __construct(AbstractSchema ...$schemas)
    {
        parent::__construct(...$schemas);
    }
}
