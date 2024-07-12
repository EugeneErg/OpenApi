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
            $result[$name] = $process->findExample($item)
                ?? ($item instanceof self ? $item->toNative($process) : $item);
        }

        return (object) $result;
    }
}
