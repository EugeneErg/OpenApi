<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi;

use EugeneErg\OpenApi\Components\Callbacks;
use EugeneErg\OpenApi\Components\Headers;
use EugeneErg\OpenApi\Components\Links;
use EugeneErg\OpenApi\Components\Parameters;
use EugeneErg\OpenApi\Components\RequestBodies;
use EugeneErg\OpenApi\Components\Responses;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchemas;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValues;
use EugeneErg\OpenApi\Components\Schemas\Object\OpenapiObject;
use EugeneErg\OpenApi\Components\Schemas\Untyped\Schemas;
use EugeneErg\OpenApi\Components\SecuritySchemes;
use stdClass;

final readonly class Components
{
    public AbstractSchemas $schemas;
    public Responses $responses;
    public Parameters $parameters;
    public AbstractValues $examples;
    public RequestBodies $requestBodies;
    public Headers $headers;
    public SecuritySchemes $securitySchemes;
    public Links $links;
    public Callbacks $callbacks;

    public function __construct(
        ?AbstractSchemas $schemas = null,
        ?Responses $responses = null,
        ?Parameters $parameters = null,
        ?OpenapiObject $examples = null,
        ?RequestBodies $requestBodies = null,
        ?Headers $headers = null,
        ?SecuritySchemes $securitySchemes = null,
        ?Links $links = null,
        ?Callbacks $callbacks = null,
    ) {
        $this->schemas = $schemas ?? new Schemas();
        $this->responses = $responses ?? new Responses();
        $this->parameters = $parameters ?? new Parameters();
        $this->examples = $examples ?? new OpenapiObject();
        $this->requestBodies = $requestBodies ?? new RequestBodies();
        $this->headers = $headers ?? new Headers();
        $this->securitySchemes = $securitySchemes ?? new SecuritySchemes();
        $this->links = $links ?? new Links();
        $this->callbacks = $callbacks ?? new Callbacks();
    }

    public function toObject(Process $process): stdClass
    {
        $result = [];

        if ($this->schemas->items !== []) {
            $result['schemas'] = $process->findSchemas($this->schemas) ?? $this->schemas->sourceToObject($process);
        }

        if ($this->responses->items !== []) {
            $result['responses'] = $process->findResponses($this->responses) ?? $this->responses->sourceToObject($process);
        }

        if ($this->parameters->items !== []) {
            $result['parameters'] = $process->findParameters($this->parameters) ?? $this->parameters->sourceToObject($process);
        }

        if ($this->examples->items !== []) {
            $result['examples'] = $process->findExamples($this->examples) ?? $this->examples->sourceToObject($process);
        }

        if ($this->requestBodies->items !== []) {
            $result['requestBodies'] = $process->findRequestBodies($this->requestBodies) ?? $this->requestBodies->sourceToObject($process);
        }

        if ($this->headers->items !== []) {
            $result['headers'] = $process->findHeaders($this->headers) ?? $this->headers->sourceToObject($process);
        }

        if ($this->securitySchemes->items !== []) {
            $result['securitySchemes'] = $process->findSecuritySchemes($this->securitySchemes) ?? $this->securitySchemes->sourceToObject();
        }

        if ($this->links->items !== []) {
            $result['links'] = $process->findLinks($this->links) ?? $this->links->sourceToObject($process);
        }

        if ($this->callbacks->items !== []) {
            $result['callbacks'] = $process->findCallbacks($this->callbacks) ?? $this->callbacks->sourceToObject($process);
        }

        return (object) $result;
    }

    public function isEmpty(): bool
    {
        return $this->schemas->items === []
            && $this->responses->items === []
            && $this->parameters->items === []
            && $this->examples->items === []
            && $this->requestBodies->items === []
            && $this->headers->items === []
            && $this->securitySchemes->items === []
            && $this->links->items === []
            && $this->callbacks->items === [];
    }
}
