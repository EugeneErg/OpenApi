<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Servers;

use EugeneErg\OpenApi\Components\Schemas\String\Strings;
use stdClass;

/**
 * Server Variable Object: подстановка в шаблон url сервера.
 */
final readonly class Variable
{
    public Strings $enum;

    public function __construct(
        public string $default,
        ?Strings $enum = null,
        public ?string $description = null,
    ) {
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

        return (object) $result;
    }
}
