<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\SecuritySchemes;

use EugeneErg\OpenApi\Extensions;
use EugeneErg\OpenApi\Process;
use stdClass;

abstract readonly class AbstractSecurityScheme
{
    public Extensions $extensions;

    public function __construct(
        public string $type,
        public ?string $description = null,
        ?Extensions $extensions = null,
    ) {
        $this->extensions = $extensions ?? new Extensions();
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

        return (object) $this->extensions->appendTo($result);
    }
}
