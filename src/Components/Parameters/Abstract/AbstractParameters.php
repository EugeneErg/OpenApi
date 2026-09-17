<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Parameters\Abstract;

use EugeneErg\OpenApi\Components\Parameters\In;
use EugeneErg\OpenApi\Process;
use EugeneErg\OpenApi\Reference;
use EugeneErg\OpenApi\Serialization\Structure;
use EugeneErg\OpenApi\Support\NamedItems;
use stdClass;

use function is_int;

abstract readonly class AbstractParameters
{
    use NamedItems;

    /** @var array<array-key, AbstractParameter|Reference> */
    public array $items;

    /**
     * Параметры без имени. Так можно передать только параметр, зарегистрированный
     * в components.parameters: имя у него берётся из регистрации, а на месте
     * использования окажется $ref. Незарегистрированный отклонит сборка.
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
            // у ссылки имя берётся из объявления компонента и рядом с $ref не пишется
            $result[] = $parameter instanceof Reference
                ? $parameter->toObject($process)
                : (object) array_merge(Structure::vars($parameter->toObject($process)), ['name' => (string) $name]);
        }

        return $result;
    }
}
