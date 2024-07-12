<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Number;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValues;

/**
 * @property float[] $items
 */
final readonly class Numbers extends AbstractValues
{
    public function __construct(float ...$items)
    {
        parent::__construct(...$items);
    }
}
