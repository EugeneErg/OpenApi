<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Parameters\Abstract;

use EugeneErg\OpenApi\Extensions;
use EugeneErg\OpenApi\Process;
use stdClass;

abstract readonly class AbstractParameter
{
    public Extensions $extensions;

    public function __construct(
        public ?string $description = null,
        public ?bool $required = false,
        public ?bool $deprecated = false,
        ?Extensions $extensions = null,
    ) {
        $this->extensions = $extensions ?? new Extensions();
    }

    public function toObject(Process $process): stdClass
    {
        $result = [];

        // required defaults to false, so there is no point in printing it;
        // on a path parameter it is always true, and there it is printed
        if ($this->required === true || $process->verbose) {
            $result['required'] = $this->required === true;
        }

        if ($this->description !== null) {
            $result['description'] = $this->description;
        }

        if ($this->deprecated === true || $process->verbose) {
            $result['deprecated'] = $this->deprecated === true;
        }

        return (object) $this->extensions->appendTo($result);
    }
}
