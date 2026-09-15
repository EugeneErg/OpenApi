<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Object;

use EugeneErg\OpenApi\Components\Schemas\String\Strings;
use stdClass;

/**
 * dependentRequired: если присутствует свойство-ключ, то обязательны перечисленные свойства.
 */
final readonly class DependentRequired
{
    /** @var array<string, Strings> */
    public array $items;

    public function __construct(Strings ...$dependencies)
    {
        /** @var array<string, Strings> $dependencies */
        $this->items = $dependencies;
    }

    public function toObject(): stdClass
    {
        $result = [];

        foreach ($this->items as $property => $required) {
            $result[$property] = array_values($required->items);
        }

        return (object) $result;
    }
}
