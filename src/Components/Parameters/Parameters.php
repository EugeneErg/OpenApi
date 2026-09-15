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
    /** @var list<AbstractParameters> */
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
        $this->items = array_values(array_filter(
            [$headers, $cookies, $paths, $queries],
            static fn (?AbstractParameters $item): bool => $item !== null,
        ));
    }

    /**
     * @return array<int, stdClass>
     */
    public function toArray(Process $process): array
    {
        $result = [];

        foreach ($this->items as $parameter) {
            $in = $parameter->in();

            foreach ($parameter->items as $name => $item) {
                $searchItem = $item instanceof AbstractSchemaParameter ? $item : new CustomParameter($in, $item);
                $result[] = $process->findParameter($searchItem)
                    ?? (object) array_merge(
                        get_object_vars($item->toObject($process)),
                        ['name' => $name, 'in' => $in->value],
                    );
            }
        }

        return $result;
    }
}
