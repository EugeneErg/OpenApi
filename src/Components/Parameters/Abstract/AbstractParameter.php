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
        $result = [];

        // required по умолчанию false, и выводить его незачем;
        // у path-параметра он всегда true, поэтому там выведется
        if ($this->required === true) {
            $result['required'] = true;
        }

        if ($this->description !== null) {
            $result['description'] = $this->description;
        }

        if ($this->deprecated === true) {
            $result['deprecated'] = true;
        }

        return (object) $result;
    }
}
