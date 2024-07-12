<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Tags;

use EugeneErg\OpenApi\ExternalDocs;
use stdClass;

final readonly class Tag
{
    public function __construct(
        public string $name,
        public ?string $description = null,
        public ?ExternalDocs $externalDocs = null,
    ) {
    }

    /**
     * @return stdClass{name: string, description?: string, externalDocs?: string[]}
     */
    public function toObject(): stdClass
    {
        $result = ['name' => $this->name];

        if ($this->description !== null) {
            $result['description'] = $this->description;
        }

        if ($this->externalDocs !== null) {
            $result['externalDocs'] = $this->externalDocs->toObject();
        }

        return (object) $result;
    }
}
