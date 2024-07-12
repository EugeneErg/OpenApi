<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Abstract;

final readonly class Xml
{
    public function __construct(
        public ?string $name = null,
        public ?string $namespace = null,
        public ?string $prefix = null,
        public bool $attribute = false,
        public bool $wrapped = false,
    ) {
    }

    /**
     * @return array{
     *     name?: string,
     *     namespace?: string,
     *     prefix?: string,
     *     attribute?: bool,
     *     wrapped?: bool,
     * }
     */
    public function toArray(): array
    {
        $result = [];

        if ($this->name !== null) {
            $result['name'] = $this->name;
        }

        if ($this->namespace !== null) {
            $result['namespace'] = $this->namespace;
        }

        if ($this->prefix !== null) {
            $result['prefix'] = $this->prefix;
        }

        if ($this->attribute) {
            $result['attribute'] = $this->attribute;
        }

        if ($this->wrapped) {
            $result['wrapped'] = $this->wrapped;
        }

        return $result;
    }
}
