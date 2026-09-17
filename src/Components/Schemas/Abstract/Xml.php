<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Abstract;

use EugeneErg\OpenApi\Extensions;
use EugeneErg\OpenApi\Process;
use stdClass;

final readonly class Xml
{
    public Extensions $extensions;

    public function __construct(
        public ?string $name = null,
        public ?string $namespace = null,
        public ?string $prefix = null,
        public bool $attribute = false,
        public bool $wrapped = false,
        ?Extensions $extensions = null,
    ) {
        $this->extensions = $extensions ?? new Extensions();
    }

    public function toObject(Process $process): stdClass
    {
        $result = [];

        if ($this->name !== null) {
            $result['name'] = $this->name;
        }

        if ($this->namespace !== null) {
            $result['namespace'] = $this->namespace;
        }

        if ($this->prefix !== null) {
            $result['prefix'] = $this->prefix;
        }

        if ($this->attribute || $process->verbose) {
            $result['attribute'] = $this->attribute;
        }

        if ($this->wrapped || $process->verbose) {
            $result['wrapped'] = $this->wrapped;
        }

        return (object) $this->extensions->appendTo($result);
    }
}
