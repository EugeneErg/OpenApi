<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Object;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValues;

/**
 * @property OpenapiObject[] $items
 */
final readonly class Objects extends AbstractValues
{
    public function __construct(OpenapiObject ...$items)
    {
        parent::__construct(...$items);
    }
}
