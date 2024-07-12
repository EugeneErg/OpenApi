<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Servers;

use EugeneErg\OpenApi\Components\Links\Link\Variables;
use EugeneErg\OpenApi\Components\Schemas\String\Strings;
use EugeneErg\OpenApi\Process;
use stdClass;

final readonly class Server
{
    public Variables $variables;
    public Strings $enum;

    public function __construct(
        public string $url,
        public ?string $description = null,
        ?Variables $variables = null,
        public ?string $default = null,
        null|Strings $enum = null,
    ) {
        $this->variables = $variables ?? new Variables();
        $this->enum = $enum ?? new Strings();
    }

    public function toObject(Process $process): stdClass
    {
        $result = [
            'url' => $this->url,
        ];

        if ($this->enum->items !== []) {
            $result['enum'] = $this->enum->items;
        }

        if ($this->description !== null) {
            $result['description'] = $this->description;
        }

        if ($this->default !== null) {
            $result['default'] = $this->default;
        }

        if ($this->variables->items !== []) {
            $result['variables'] = $this->variables->toArray($process);
        }

        return (object) $result;
    }
}
