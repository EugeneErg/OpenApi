<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Parameters\Abstract;

use EugeneErg\OpenApi\Process;
use stdClass;

abstract readonly class AbstractParameter
{
    public function __construct(
        public ?string $description = null,
        public ?bool $required = false,
        public ?bool $deprecated = false,
    ) {
    }

    public function toObject(Process $process): stdClass
    {
        $result = ['required' => $this->required];

        if ($this->description !== null) {
            $result['description'] = $this->description;
        }

        if ($this->description) {
            $result['description'] = $this->description;
        }

        return (object) $result;
    }
}
