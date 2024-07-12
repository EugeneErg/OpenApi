<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Paths;

use EugeneErg\OpenApi\Components\Callbacks;
use EugeneErg\OpenApi\Components\Parameters\Parameters;
use EugeneErg\OpenApi\Components\RequestBodies\RequestBody;
use EugeneErg\OpenApi\Components\Responses;
use EugeneErg\OpenApi\ExternalDocs;
use EugeneErg\OpenApi\Process;
use EugeneErg\OpenApi\Securities;
use EugeneErg\OpenApi\Servers;
use EugeneErg\OpenApi\Tags;
use stdClass;

final readonly class Operation
{
    public Parameters $parameters;
    public Tags $tags;
    public Securities $security;
    public Servers $servers;
    public Callbacks $callbacks;

    public function __construct(
        public Responses $responses,
        public ?string $summary = null,
        public ?string $description = null,
        public ?string $id = null,
        public bool $deprecated = false,
        ?Parameters $parameters = null,
        public ?RequestBody $requestBody = null,
        ?Tags $tags = null,
        ?Securities $security = null,
        ?Servers $servers = null,
        ?Callbacks $callbacks = null,
        public ?ExternalDocs $externalDocs = null,
    ) {
        $this->parameters = $parameters ?? new Parameters();
        $this->tags = $tags ?? new Tags();
        $this->security = $security ?? new Securities();
        $this->servers = $servers ?? new Servers();
        $this->callbacks = $callbacks ?? new Callbacks();
    }

    public function toObject(Process $process): stdClass
    {
        $result = [
            'responses' => $this->responses->toObject($process),
        ];

        if ($this->summary !== null) {
            $result['summary'] = $this->summary;
        }

        if ($this->description !== null) {
            $result['description'] = $this->description;
        }

        if ($this->id !== null) {
            $result['operationId'] = $this->id;
        }

        if ($this->deprecated) {
            $result['deprecated'] = $this->deprecated;
        }

        if ($this->parameters->items !== []) {
            $result['parameters'] = $this->parameters->toArray($process);
        }

        if ($this->requestBody !== null) {
            $result['requestBody'] = $this->requestBody->toObject($process);
        }

        if ($this->tags->items !== []) {
            $result['tags'] = $this->tags->toArray();
        }

        if ($this->security->items !== []) {
            $result['security'] = $this->security->toArray($process);
        }

        if ($this->servers->items !== []) {
            $result['servers'] = $this->servers->toArray($process);
        }

        if ($this->callbacks->items !== []) {
            $result['callbacks'] = $this->callbacks->toObject($process);
        }

        if ($this->externalDocs !== null) {
            $result['externalDocs'] = $this->externalDocs->toObject();
        }

        return (object) $result;
    }
}
