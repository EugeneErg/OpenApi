<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Abstract;

use EugeneErg\OpenApi\ExternalDocs;
use EugeneErg\OpenApi\Process;
use stdClass;

abstract readonly class AbstractSchema
{
    public function __construct(
        public ?string $type = null,
        public ?string $title = null,
        public ?string $description = null,
        public bool $nullable = false,
        public ?Access $access = null,
        public bool $deprecated = false,
        public ?ExternalDocs $externalDocs = null,
        public ?Xml $xml = null,
        public ?AbstractValue $default = null,
    ) {
    }

    public function toObject(Process $process): stdClass
    {
        $result = [];

        if ($this->type !== null) {
            $result['type'] = $this->type;
        }

        if ($this->title !== null) {
            $result['title'] = $this->title;
        }

        if ($this->description !== null) {
            $result['description'] = $this->description;
        }

        if ($this->nullable) {
            $result['nullable'] = $this->nullable;
        }

        if ($this->access !== null) {
            $result[$this->access->value] = true;
        }

        if ($this->externalDocs !== null) {
            $result['externalDocs'] = $this->externalDocs;
        }

        if ($this->xml !== null) {
            $result['xml'] = $this->xml->toObject();
        }

        if ($this->default !== null) {
            $result['default'] = $this->default->toNative($process);
        }

        return (object) $result;
    }
}
