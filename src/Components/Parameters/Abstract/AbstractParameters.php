<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Parameters\Abstract;

use EugeneErg\OpenApi\Components\Parameters\In;
use EugeneErg\OpenApi\Process;
use EugeneErg\OpenApi\Serialization\Structure;
use stdClass;

abstract readonly class AbstractParameters
{
    /** @var array<string, AbstractParameter> */
    public array $items;

    public function __construct(AbstractParameter ...$parameters)
    {
        /** @var array<string, AbstractParameter> $parameters */
        $this->items = $parameters;
    }

    /**
     * Раздел спецификации, к которому относится контейнер. Благодаря этому
     * Parameters не нуждается в таблице соответствия «имя свойства => in».
     */
    abstract public function in(): In;

    /**
     * @return array<int, stdClass>
     */
    public function toArray(Process $process): array
    {
        $result = [];

        foreach ($this->items as $name => $parameter) {
            $result[] = (object) array_merge(Structure::vars($parameter->toObject($process)), ['name' => $name]);
        }

        return $result;
    }
}
