<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Abstract;

use EugeneErg\OpenApi\Process;
use stdClass;

abstract readonly class AbstractValue
{
    public function __construct(public AbstractValues|bool|float|int|string|null $value)
    {
    }

    /**
     * @return null|array{}|bool|float|int|stdClass|string
     */
    public function toNative(Process $process): array|bool|float|int|stdClass|string|null
    {
        return $this->value instanceof AbstractValues ? $this->value->toNative($process) : $this->value;
    }
}
