<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Servers;

use EugeneErg\OpenApi\Components\Schemas\String\Strings;
use EugeneErg\OpenApi\Extensions;
use stdClass;

/**
 * Server Variable Object: a substitution in a server's url template.
 */
final readonly class Variable
{
    public Extensions $extensions;

    public Strings $enum;

    public function __construct(
        public string $default,
        ?Strings $enum = null,
        public ?string $description = null,
        ?Extensions $extensions = null,
    ) {
        $this->extensions = $extensions ?? new Extensions();

        $this->enum = $enum ?? new Strings();
    }

    public function toObject(): stdClass
    {
        $result = [];

        if ($this->enum->items !== []) {
            $result['enum'] = array_values($this->enum->items);
        }

        $result['default'] = $this->default;

        if ($this->description !== null) {
            $result['description'] = $this->description;
        }

        return (object) $this->extensions->appendTo($result);
    }
}
