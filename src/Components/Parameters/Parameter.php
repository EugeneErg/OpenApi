<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Parameters;

use EugeneErg\OpenApi\Components\Parameters\Cookie\SchemaParameter as CookieSchemaParameter;
use EugeneErg\OpenApi\Components\Parameters\Header\SchemaParameter as HeaderSchemaParameter;
use EugeneErg\OpenApi\Components\Parameters\Path\SchemaParameter as PathSchemaParameter;
use EugeneErg\OpenApi\Components\Parameters\Query\SchemaParameter as QuerySchemaParameter;
use EugeneErg\OpenApi\Process;
use stdClass;

final class Parameter
{
    public function __construct(
        public string $name,
        public CustomParameter|PathSchemaParameter|CookieSchemaParameter|QuerySchemaParameter|HeaderSchemaParameter $parameter,
    ) {
    }

    public function toObject(Process $process): stdClass
    {
        return (object) array_merge($this->getParameterArray($process), ['name' => $this->name]);
    }

    /**
     * @param Process $process
     *
     * @return array<string, mixed>
     */
    private function getParameterArray(Process $process): array
    {
        if ($this->parameter instanceof PathSchemaParameter) {
            return array_merge((array) $this->parameter->toObject($process), ['in' => In::Path->value]);
        }

        if ($this->parameter instanceof CookieSchemaParameter) {
            return array_merge((array) $this->parameter->toObject($process), ['in' => In::Cookie->value]);
        }

        if ($this->parameter instanceof QuerySchemaParameter) {
            return array_merge((array) $this->parameter->toObject($process), ['in' => In::Query->value]);
        }

        if ($this->parameter instanceof HeaderSchemaParameter) {
            return array_merge((array) $this->parameter->toObject($process), ['in' => In::Header->value]);
        }

        return (array) $this->parameter->toObject($process);
    }
}
