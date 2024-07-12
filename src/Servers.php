<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi;

use EugeneErg\OpenApi\Servers\Server;
use stdClass;

final readonly class Servers
{
    /** @var array<Server> */
    public array $items;

    public function __construct(Server ...$servers)
    {
        $this->items = $servers;
    }

    /**
     * @return array<int, stdClass>
     */
    public function toArray(Process $process): array
    {
        $result = [];

        foreach ($this->items as $server) {
            $result[] = $server->toObject($process);
        }

        return $result;
    }
}
