<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Object;

use EugeneErg\OpenApi\Components\Schemas\String\Strings;
use EugeneErg\OpenApi\Support\NamedItems;
use stdClass;

/**
 * dependentRequired: если присутствует свойство-ключ, то обязательны перечисленные свойства.
 */
final readonly class DependentRequired
{
    use NamedItems;

    /** @var array<array-key, Strings> */
    public array $items;

    public function __construct(Strings ...$dependencies)
    {
        $this->items = self::named($dependencies);
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
