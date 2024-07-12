<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Paths;

use EugeneErg\OpenApi\Components\Parameters\Parameters;
use EugeneErg\OpenApi\Process;
use EugeneErg\OpenApi\Servers;
use stdClass;

final readonly class Path
{
    /** @var array<string, Operation> */
    public array $operations;
    public Servers $servers;
    public Parameters $parameters;

    public function __construct(
        public ?Operation $get = null,
        public ?Operation $put = null,
        public ?Operation $post = null,
        public ?Operation $delete = null,
        public ?Operation $options = null,
        public ?Operation $head = null,
        public ?Operation $patch = null,
        public ?Operation $trace = null,
        ?Servers $servers = null,
        ?Parameters $parameters = null,
    ) {
        $this->operations = array_filter([
            'get' => $this->get,
            'put' => $this->put,
            'post' => $this->post,
            'delete' => $this->delete,
            'options' => $this->options,
            'head' => $this->head,
            'patch' => $this->patch,
            'trace' => $this->trace,
        ], static fn (?Operation $method) => $method !== null);
        $this->servers = $servers ?? new Servers();
        $this->parameters = $parameters ?? new Parameters();
    }

    /**
     * @return stdClass{
     *     get?: object{},
     *     put?: object{},
     *     post?: object{},
     *     delete?: object{},
     *     options?: object{},
     *     head?: object{},
     *     patch?: object{},
     *     trace?: object{},
     *     servers?: array{},
     *     parameters?: array{},
     * }
     */
    public function toObject(Process $process): stdClass
    {
        $result = [];

        foreach ($this->operations as $name => $method) {
            $result[$name] = $method->toObject($process);
        }

        if ($this->servers->items !== []) {
            $result['servers'] = $this->servers->toArray($process);
        }

        if ($this->parameters->items !== []) {
            $result['parameters'] = $this->parameters->toArray($process);
        }

        return (object) $result;
    }
}
