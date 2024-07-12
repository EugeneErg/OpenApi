<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Abstract;

use EugeneErg\OpenApi\Process;
use stdClass;

abstract readonly class AbstractValue
{
    public function __construct(public int|float|string|bool|null|AbstractValues $value)
    {
    }

    /**
     * @return int|float|string|bool|stdClass|array{}|null
     */
    public function toNative(Process $process): int|float|string|bool|null|stdClass|array
    {
        return $process->findExample($this->value)
            ?? ($this->value instanceof AbstractValues ? $this->value->toNative($process) : $this->value);
    }
}
