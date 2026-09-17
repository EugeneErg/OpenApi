<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Abstract;

use EugeneErg\OpenApi\Exceptions\InvalidArgumentOpenapiException;
use EugeneErg\OpenApi\Exceptions\Place;
use EugeneErg\OpenApi\Process;
use EugeneErg\OpenApi\Support\NamedItems;
use stdClass;

use function count;
use function is_int;
use function sprintf;

abstract readonly class AbstractSchemas
{
    use NamedItems;

    /** @var AbstractSchema[] */
    public array $items;

    /**
     * The container serves both as a map (components.schemas, mapping, $defs) and as a
     * list (allOf, anyOf, oneOf, prefixItems). Which one it is, the place of use decides
     * through assertNamed() and assertListed(); what is recorded here is only how it was
     * filled.
     */
    private bool $listed;

    public function __construct(AbstractSchema ...$schemas)
    {
        $positional = array_filter(array_keys($schemas), is_int(...));

        if ($positional !== [] && count($positional) !== count($schemas)) {
            throw self::positional();
        }

        $this->listed = $positional !== [];
        $this->items = $this->listed ? array_values($schemas) : self::named($schemas);
    }

    public function assertNamed(string $where): void
    {
        if ($this->listed) {
            throw new InvalidArgumentOpenapiException(sprintf(
                '%s needs schemas by name. %s',
                $where,
                self::positional()->getMessage(),
            ));
        }
    }

    public function assertListed(string $where): void
    {
        if (!$this->listed && $this->items !== []) {
            throw new InvalidArgumentOpenapiException(sprintf(
                '%s is a list of schemas: names are not allowed, got "%s".',
                $where,
                (string) array_key_first($this->items),
            ));
        }
    }

    /**
     * @return array<int, stdClass>
     */
    public function toArray(Process $process): array
    {
        $result = [];

        foreach ($this->items as $index => $item) {
            $result[] = Place::in(
                static fn (): stdClass => $process->findSchema($item) ?? $item->toObject($process),
                $index,
            );
        }

        return $result;
    }

    public function toObject(Process $process): stdClass
    {
        $result = [];

        foreach ($this->items as $name => $item) {
            $result[$name] = Place::in(
                static fn (): stdClass => $process->findSchema($item) ?? $item->toObject($process),
                $name,
            );
        }

        return (object) $result;
    }

    public function sourceToObject(Process $process): stdClass
    {
        $result = [];

        foreach ($this->items as $name => $item) {
            $result[$name] = Place::in(static fn (): stdClass => $item->toObject($process), $name);
        }

        return (object) $result;
    }
}
