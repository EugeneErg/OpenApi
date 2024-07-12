<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Links;

use EugeneErg\OpenApi\Components\Links\Link\Parameter;
use EugeneErg\OpenApi\Components\Links\Link\Parameters;
use EugeneErg\OpenApi\Paths\Operation;
use EugeneErg\OpenApi\Process;
use EugeneErg\OpenApi\Servers\Server;
use stdClass;

final readonly class Link
{
    public Parameters $parameters;
    public Parameters|Parameter $requestBody;

    public function __construct(
        public Operation $operation,
        ?Parameters $parameters = null,
        null|Parameters|Parameter $requestBody = null,
        public ?string $description = null,
        public ?Server $server = null,
    ) {
        $this->parameters = $parameters ?? new Parameters();
        $this->requestBody = $requestBody ?? new Parameters();
    }

    public function toObject(Process $process): stdClass
    {
        $result = [];

        if (isset($this->operation->id)) {
            $result['operationId'] = $this->operation->id;
        } else {
            $result['operationRef'] = $process->findOperation($this->operation);
        }


        return (object) $result;
    }
}
