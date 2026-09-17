<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Paths;

use EugeneErg\OpenApi\Components\Callbacks;
use EugeneErg\OpenApi\Components\Parameters\Parameters;
use EugeneErg\OpenApi\Components\RequestBodies\RequestBody;
use EugeneErg\OpenApi\Components\Responses;
use EugeneErg\OpenApi\Exceptions\InvalidArgumentOpenapiException;
use EugeneErg\OpenApi\Exceptions\Place;
use EugeneErg\OpenApi\Extensions;
use EugeneErg\OpenApi\ExternalDocs;
use EugeneErg\OpenApi\Process;
use EugeneErg\OpenApi\Reference;
use EugeneErg\OpenApi\Securities;
use EugeneErg\OpenApi\Servers;
use EugeneErg\OpenApi\Tags;
use stdClass;

use function sprintf;

final readonly class Operation
{
    public Extensions $extensions;

    public Parameters $parameters;
    public Tags $tags;
    public Servers $servers;
    public Callbacks $callbacks;

    public function __construct(
        /** Required in 3.0, and may be absent in 3.1. */
        public ?Responses $responses = null,
        public ?string $summary = null,
        public ?string $description = null,
        public ?string $id = null,
        public bool $deprecated = false,
        ?Parameters $parameters = null,
        public Reference|RequestBody|null $requestBody = null,
        ?Tags $tags = null,
        /**
         * null means inherit the document's `security`; `new Securities()` means the
         * operation is open even when the document level requires authorisation.
         */
        public ?Securities $security = null,
        ?Servers $servers = null,
        ?Callbacks $callbacks = null,
        public ?ExternalDocs $externalDocs = null,
        ?Extensions $extensions = null,
    ) {
        $this->extensions = $extensions ?? new Extensions();

        if ($responses !== null && $responses->items === []) {
            throw new InvalidArgumentOpenapiException('Operation must declare at least one response.');
        }

        foreach (array_keys($responses->items ?? []) as $code) {
            // x200 / x4XX is how a code is written as a named argument; '200' comes through fromArray()
            if (preg_match('{^(?:x?[1-5](?:\d\d|XX)|default)$}', (string) $code) !== 1) {
                throw new InvalidArgumentOpenapiException(sprintf(
                    'Response key "%s" is neither an HTTP status code (200, 4XX; x200 as a named argument) nor "default".',
                    $code,
                ));
            }
        }

        $this->parameters = $parameters ?? new Parameters();
        $this->tags = $tags ?? new Tags();
        $this->servers = $servers ?? new Servers();
        $this->callbacks = $callbacks ?? new Callbacks();
    }

    public function toObject(Process $process): stdClass
    {
        $result = [];

        if ($this->responses !== null) {
            $result['responses'] = Place::in(
                fn (): stdClass => $this->responses->toObject($process),
                'responses',
            );
        } elseif (!$process->version()->isV31()) {
            throw new InvalidArgumentOpenapiException(sprintf(
                'Operation%s must declare responses in OpenAPI %s; they became optional in 3.1.',
                $this->id === null ? '' : sprintf(' "%s"', $this->id),
                $process->version()->value,
            ));
        }

        if ($this->summary !== null) {
            $result['summary'] = $this->summary;
        }

        if ($this->description !== null) {
            $result['description'] = $this->description;
        }

        if ($this->id !== null) {
            $result['operationId'] = $this->id;
        }

        if ($this->deprecated || $process->verbose) {
            $result['deprecated'] = $this->deprecated;
        }

        if ($this->parameters->items !== []) {
            $result['parameters'] = Place::in(
                fn (): array => $this->parameters->toArray($process),
                'parameters',
            );
        }

        if ($this->requestBody !== null) {
            // a body declared in components.requestBodies is written as a reference here
            $result['requestBody'] = Place::in(
                fn (): stdClass => $this->requestBody instanceof Reference
                    ? $this->requestBody->toObject($process)
                    : ($process->findRequestBody($this->requestBody) ?? $this->requestBody->toObject($process)),
                'requestBody',
            );
        }

        if ($this->tags->items !== []) {
            $result['tags'] = $this->tags->toNames();
        }

        if ($this->security !== null) {
            $result['security'] = $this->security->toArray($process);
        }

        if ($this->servers->items !== []) {
            $result['servers'] = $this->servers->toArray();
        }

        if ($this->callbacks->items !== []) {
            $result['callbacks'] = Place::in(
                fn (): stdClass => $this->callbacks->toObject($process),
                'callbacks',
            );
        }

        if ($this->externalDocs !== null) {
            $result['externalDocs'] = $this->externalDocs->toObject();
        }

        return (object) $this->extensions->appendTo($result);
    }
}
