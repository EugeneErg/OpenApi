<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Links;

use EugeneErg\OpenApi\Components\Links\Link\Parameters;
use EugeneErg\OpenApi\Components\RequestBodies\RequestBody;
use EugeneErg\OpenApi\Extensions;
use EugeneErg\OpenApi\Paths\DeferredOperation;
use EugeneErg\OpenApi\Paths\Operation;
use EugeneErg\OpenApi\Process;
use EugeneErg\OpenApi\Servers\Server;
use stdClass;

final readonly class Link
{
    public Extensions $extensions;

    public Parameters $parameters;

    public function __construct(
        public DeferredOperation|Operation $operation,
        ?Parameters $parameters = null,
        public ?RequestBody $requestBody = null,
        public ?string $description = null,
        public ?Server $server = null,
        ?Extensions $extensions = null,
    ) {
        $this->extensions = $extensions ?? new Extensions();

        $this->parameters = $parameters ?? new Parameters();
    }

    public function toObject(Process $process): stdClass
    {
        $result = [];
        $operation = $this->operation();

        // операция названа своим operationId, иначе — указателем на место в paths
        if ($operation->id !== null) {
            $result['operationId'] = $operation->id;
        } else {
            $result['operationRef'] = $process->findOperation($operation);
        }

        if ($this->description !== null) {
            $result['description'] = $this->description;
        }

        if ($this->parameters->items !== []) {
            $result['parameters'] = $this->parameters->toObject();
        }

        if ($this->requestBody !== null) {
            $result['requestBody'] = $this->requestBody->toObject($process);
        }

        if ($this->server !== null) {
            $result['server'] = $this->server->toObject();
        }

        return (object) $this->extensions->appendTo($result);
    }

    /**
     * Операция, на которую ведёт ссылка: отложенная разворачивается здесь.
     */
    public function operation(): Operation
    {
        return $this->operation instanceof DeferredOperation ? $this->operation->resolve() : $this->operation;
    }
}
