<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Tags;

use EugeneErg\OpenApi\Extensions;
use EugeneErg\OpenApi\ExternalDocs;
use stdClass;

final readonly class Tag
{
    public Extensions $extensions;

    public function __construct(
        public string $name,
        public ?string $description = null,
        public ?ExternalDocs $externalDocs = null,
        ?Extensions $extensions = null,
    ) {
        $this->extensions = $extensions ?? new Extensions();
    }

    public function toObject(): stdClass
    {
        $result = ['name' => $this->name];

        if ($this->description !== null) {
            $result['description'] = $this->description;
        }

        if ($this->externalDocs !== null) {
            $result['externalDocs'] = $this->externalDocs->toObject();
        }

        return (object) $this->extensions->appendTo($result);
    }
}
