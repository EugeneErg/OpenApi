<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Links\Link;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValue;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValues;
use EugeneErg\OpenApi\Process;

final readonly class Variable
{
    public function __construct(
        public AbstractValue $default,
        public ?string $description = null,
    ) {
    }

    /**
     * @return array{default: mixed, description?: string}
     */
    public function toArray(Process $process): array
    {
        $result = [
            'default' => $this->default->value instanceof AbstractValues
                ? $this->default->value->toNative($process)
                : $this->default->value,
        ];

        if ($this->description !== null) {
            $result['description'] = $this->description;
        }

        return $result;
    }
}
