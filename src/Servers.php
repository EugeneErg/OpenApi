<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi;

use EugeneErg\OpenApi\Servers\Server;
use EugeneErg\OpenApi\Support\ListedItems;
use stdClass;

final readonly class Servers
{
    use ListedItems;

    /** @var array<Server> */
    public array $items;

    public function __construct(Server ...$servers)
    {
        $this->items = self::listed($servers);
    }

    /**
     * @return array<int, stdClass>
     */
    public function toArray(): array
    {
        $result = [];

        foreach ($this->items as $server) {
            $result[] = $server->toObject();
        }

        return $result;
    }
}
