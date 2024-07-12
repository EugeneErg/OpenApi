<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Parameters;

use EugeneErg\OpenApi\Components\Parameters\Abstract\AbstractParameters;
use EugeneErg\OpenApi\Components\Parameters\Abstract\AbstractSchemaParameter;
use EugeneErg\OpenApi\Components\Parameters\Cookie\Cookies;
use EugeneErg\OpenApi\Components\Parameters\Header\Headers;
use EugeneErg\OpenApi\Components\Parameters\Path\Paths;
use EugeneErg\OpenApi\Components\Parameters\Query\Queries;
use EugeneErg\OpenApi\Process;
use stdClass;

final readonly class Parameters
{
    /** @var array<string, AbstractParameters> */
    public array $items;
    public Headers $headers;
    public Cookies $cookies;
    public Paths $paths;
    public Queries $queries;

    public function __construct(
        ?Headers $headers = null,
        ?Cookies $cookies = null,
        ?Paths $paths = null,
        ?Queries $queries = null,
    ) {
        $this->headers = $headers ?? new Headers();
        $this->cookies = $cookies ?? new Cookies();
        $this->paths = $paths ?? new Paths();
        $this->queries = $queries ?? new Queries();
        /** @var array<string, AbstractParameters> $items */
        $items = array_filter(func_get_args(), static fn (mixed $item) => $item instanceof AbstractParameters);
        $this->items = $items;
    }

    /**
     * @return array<int, stdClass>
     */
    public function toArray(Process $process): array
    {
        $result = [];

        foreach ($this->items as $type => $parameter) {
            $realType = ['headers' => 'header', 'cookies' => 'cookie', 'paths' => 'path', 'queries' => 'query'][$type];
            $in = In::from($realType);

            /** @var AbstractSchemaParameter|ContentParameter $item */
            foreach ($parameter->items as $name => $item) {
                $searchItem = $item instanceof AbstractSchemaParameter ? $item : new CustomParameter($in, $item);
                $result[] = $process->findParameter($searchItem)
                    ?? (object) array_merge((array) $item->toObject($process), ['name' => $name, 'in' => $realType]);
            }
        }

        return $result;
    }
}
