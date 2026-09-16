<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Object;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValues;
use EugeneErg\OpenApi\Process;
use stdClass;

final readonly class OpenapiObject extends AbstractValues
{
    public function toNative(Process $process): stdClass
    {
        $result = [];

        foreach ($this->items as $name => $item) {
            // рекурсия по любому контейнеру: вложенный список — это AbstractValues, а не self
            $result[$name] = $item instanceof AbstractValues ? $item->toNative($process) : $item;
        }

        return (object) $result;
    }
}
