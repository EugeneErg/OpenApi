<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Servers;

use EugeneErg\OpenApi\Extensions;
use stdClass;

final readonly class Server
{
    public Extensions $extensions;

    public Variables $variables;

    public function __construct(
        public string $url,
        public ?string $description = null,
        ?Variables $variables = null,
        ?Extensions $extensions = null,
    ) {
        $this->extensions = $extensions ?? new Extensions();

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

        return (object) $this->extensions->appendTo($result);
    }
}
