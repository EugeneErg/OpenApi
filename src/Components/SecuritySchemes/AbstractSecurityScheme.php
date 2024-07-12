<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\SecuritySchemes;

use EugeneErg\OpenApi\Process;
use stdClass;

abstract readonly class AbstractSecurityScheme
{
    public function __construct(
        public string $type,
        public ?string $description = null,
    ) {
    }

    public function toTargetArray(Process $process): stdClass
    {
        return $process->findSecurity($this);
    }

    public function toObject(): stdClass
    {
        $result = [
            'type' => $this->type,
        ];

        if ($this->description !== null) {
            $result['description'] = $this->description;
        }

        return (object) $result;
    }
}
