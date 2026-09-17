<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Parameters\Abstract;

use EugeneErg\OpenApi\Components\Parameters\In;
use EugeneErg\OpenApi\Reference;
use EugeneErg\OpenApi\Support\NamedItems;

use function is_int;

abstract readonly class AbstractParameters
{
    use NamedItems;

    /** @var array<array-key, AbstractParameter|Reference> */
    public array $items;

    /**
     * The parameters passed without a name. Only a parameter registered in
     * components.parameters can be passed that way: its name comes from the registration,
     * and a $ref stands where it is used. The build rejects an unregistered one.
     *
     * @var list<AbstractParameter|Reference>
     */
    public array $registered;

    public function __construct(AbstractParameter|Reference ...$parameters)
    {
        $named = [];
        $registered = [];

        foreach ($parameters as $name => $parameter) {
            if (is_int($name)) {
                $registered[] = $parameter;
            } else {
                $named[$name] = $parameter;
            }
        }

        $this->items = self::named($named);
        $this->registered = $registered;
    }

    /**
     * The section of the specification the container belongs to. Thanks to it Parameters
     * needs no table mapping a property name to an `in` value.
     */
    abstract public function in(): In;
}
