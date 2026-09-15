<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Servers;

use stdClass;

final readonly class Server
{
    public Variables $variables;

    public function __construct(
        public string $url,
        public ?string $description = null,
        ?Variables $variables = null,
    ) {
        $this->variables = $variables ?? new Variables();
    }

    public function toObject(): stdClass
    {
        $result = ['url' => $this->url];

        if ($this->description !== null) {
            $result['description'] = $this->description;
        }

        if ($this->variables->items !== []) {
            $result['variables'] = $this->variables->toObject();
        }

        return (object) $result;
    }
}
