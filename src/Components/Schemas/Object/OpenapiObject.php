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
            // recursion over any container: a nested list is an AbstractValues, not a self
            $result[$name] = $item instanceof AbstractValues ? $item->toNative($process) : $item;
        }

        return (object) $result;
    }

    protected static function isMap(): bool
    {
        return true;
    }
}
