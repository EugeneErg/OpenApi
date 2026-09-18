<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi;

use EugeneErg\OpenApi\Components\Callbacks;
use EugeneErg\OpenApi\Components\Examples;
use EugeneErg\OpenApi\Components\Headers;
use EugeneErg\OpenApi\Components\Links;
use EugeneErg\OpenApi\Components\Parameters;
use EugeneErg\OpenApi\Components\RequestBodies;
use EugeneErg\OpenApi\Components\Responses;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchemas;
use EugeneErg\OpenApi\Components\Schemas\Untyped\Schemas;
use EugeneErg\OpenApi\Components\SecuritySchemes;
use EugeneErg\OpenApi\Exceptions\InvalidArgumentOpenapiException;
use EugeneErg\OpenApi\Exceptions\Place;
use stdClass;

use function sprintf;

final readonly class Components
{
    public Extensions $extensions;

    public AbstractSchemas $schemas;
    public Responses $responses;
    public Parameters $parameters;
    public Examples $examples;
    public PathItems $pathItems;
    public RequestBodies $requestBodies;
    public Headers $headers;
    public SecuritySchemes $securitySchemes;
    public Links $links;
    public Callbacks $callbacks;

    public function __construct(
        ?Examples $examples = null,
        ?PathItems $pathItems = null,
        ?AbstractSchemas $schemas = null,
        ?Parameters $parameters = null,
        ?Headers $headers = null,
        ?RequestBodies $requestBodies = null,
        ?Responses $responses = null,
        ?SecuritySchemes $securitySchemes = null,
        ?Links $links = null,
        ?Callbacks $callbacks = null,
        ?Extensions $extensions = null,
    ) {
        $this->extensions = $extensions ?? new Extensions();

        $this->schemas = $schemas ?? new Schemas();
        $this->responses = $responses ?? new Responses();
        $this->parameters = $parameters ?? new Parameters();
        $this->examples = $examples ?? new Examples();
        $this->pathItems = $pathItems ?? new PathItems();
        $this->requestBodies = $requestBodies ?? new RequestBodies();
        $this->headers = $headers ?? new Headers();
        $this->securitySchemes = $securitySchemes ?? new SecuritySchemes();
        $this->links = $links ?? new Links();
        $this->callbacks = $callbacks ?? new Callbacks();

        $this->schemas->assertNamed('components.schemas');

        foreach ([
            'responses' => $this->responses->extensions,
            'pathItems' => $this->pathItems->extensions,
        ] as $section => $extensions) {
            if ($extensions->items !== []) {
                throw new InvalidArgumentOpenapiException(sprintf(
                    'components.%s is a plain map: its extensions belong to the Components object itself.',
                    $section,
                ));
            }
        }

        foreach ([
            'schemas' => $this->schemas->items,
            'responses' => $this->responses->items,
            'parameters' => $this->parameters->items,
            'examples' => $this->examples->items,
            'pathItems' => $this->pathItems->items,
            'requestBodies' => $this->requestBodies->items,
            'headers' => $this->headers->items,
            'securitySchemes' => $this->securitySchemes->items,
            'links' => $this->links->items,
            'callbacks' => $this->callbacks->items,
        ] as $section => $items) {
            foreach (array_keys($items) as $name) {
                if (preg_match('{^[a-zA-Z0-9._-]+$}', (string) $name) !== 1) {
                    throw new InvalidArgumentOpenapiException(sprintf(
                        'Component name "%s" in components.%s must match ^[a-zA-Z0-9._-]+$.',
                        $name,
                        $section,
                    ));
                }
            }
        }
    }

    /**
     * Every section is written out where it stands.
     *
     * Two documents may be handed the same container object, and that used to be printed
     * as one reference to the whole section — `other.json#/components/schemas`. The
     * specification has no place for a reference there: `schemas` is a
     * `Map[string, Schema Object]`, so a `$ref` in it is the name of a component rather
     * than a reference, and the package's own reader said exactly that when it read the
     * document back. Sharing the items is what makes a `$ref`; sharing the container just
     * means both documents declare the same components.
     */
    public function toObject(Process $process): stdClass
    {
        $result = [];

        if ($this->schemas->items !== []) {
            $result['schemas'] = Place::in(
                fn (): stdClass => $this->schemas->sourceToObject($process),
                'components',
                'schemas',
            );
        }

        if ($this->responses->items !== []) {
            $result['responses'] = Place::in(
                fn (): stdClass => $this->responses->sourceToObject($process),
                'components',
                'responses',
            );
        }

        if ($this->parameters->items !== []) {
            $result['parameters'] = Place::in(
                fn (): stdClass => $this->parameters->sourceToObject($process),
                'components',
                'parameters',
            );
        }

        if ($this->examples->items !== []) {
            $result['examples'] = Place::in(
                fn (): stdClass => $this->examples->sourceToObject($process),
                'components',
                'examples',
            );
        }

        if ($this->requestBodies->items !== []) {
            $result['requestBodies'] = Place::in(
                fn (): stdClass => $this->requestBodies->sourceToObject($process),
                'components',
                'requestBodies',
            );
        }

        if ($this->headers->items !== []) {
            $result['headers'] = Place::in(
                fn (): stdClass => $this->headers->sourceToObject($process),
                'components',
                'headers',
            );
        }

        if ($this->securitySchemes->items !== []) {
            $result['securitySchemes'] = Place::in(
                fn (): stdClass => $this->securitySchemes->sourceToObject(),
                'components',
                'securitySchemes',
            );
        }

        if ($this->links->items !== []) {
            $result['links'] = Place::in(
                fn (): stdClass => $this->links->sourceToObject($process),
                'components',
                'links',
            );
        }

        if ($this->callbacks->items !== []) {
            $result['callbacks'] = Place::in(
                fn (): stdClass => $this->callbacks->sourceToObject($process),
                'components',
                'callbacks',
            );
        }

        if ($this->pathItems->items !== []) {
            $result['pathItems'] = $this->pathItems->sourceToObject($process);
        }

        return (object) $this->extensions->appendTo($result);
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
            && $this->callbacks->items === []
            && $this->pathItems->items === [];
    }
}
