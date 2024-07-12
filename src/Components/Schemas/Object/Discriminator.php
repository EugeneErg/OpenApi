<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Object;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchemas;
use EugeneErg\OpenApi\Components\Schemas\Untyped\Schemas;

abstract readonly class Discriminator
{
    public AbstractSchemas $mapping;

    public function __construct(
        public string $propertyName,
        ?AbstractSchemas $mapping = null,//далее преобразовывать в названия схем
    ) {
        $this->mapping = $mapping ?? new Schemas();
    }
}
