<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Links;

use EugeneErg\OpenApi\Components\Links\Link\Parameters;
use EugeneErg\OpenApi\Components\RequestBodies\RequestBody;
use EugeneErg\OpenApi\Paths\Operation;
use EugeneErg\OpenApi\Process;
use EugeneErg\OpenApi\Servers\Server;
use stdClass;

final readonly class Link
{
    public Parameters $parameters;

    public function __construct(
        public Operation $operation,
        ?Parameters $parameters = null,
        public null|RequestBody $requestBody = null,
        public ?string $description = null,
        public ?Server $server = null,
    ) {
        $this->parameters = $parameters ?? new Parameters();
    }

    public function toObject(Process $process): stdClass
    {
        $result = [];

        if (isset($this->operation->id)) {
            $result['operationId'] = $this->operation->id;
        } else {
            $result['operationRef'] = $process->findOperation($this->operation);
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

        return (object) $result;
    }
}
