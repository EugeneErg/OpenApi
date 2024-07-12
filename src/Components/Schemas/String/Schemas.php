<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\String;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchemas;

/**
 * @property array<Schema|EnumSchema> $items
 */
final readonly class Schemas extends AbstractSchemas
{
    public function __construct(Schema|EnumSchema ...$schemas)
    {
        parent::__construct(...$schemas);
    }
}
