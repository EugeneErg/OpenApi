<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Responses;

use EugeneErg\OpenApi\Components\Headers;
use EugeneErg\OpenApi\Components\Links;
use EugeneErg\OpenApi\Components\RequestBodies\Contents;
use EugeneErg\OpenApi\Process;
use stdClass;

final readonly class Response
{
    public Headers $headers;
    public Contents $content;
    public Links $links;

    public function __construct(
        public string $description,
        ?Headers $headers = null,
        ?Contents $content = null,
        ?Links $links = null,
    ) {
        $this->headers = $headers ?? new Headers();
        $this->content = $content ?? new Contents();
        $this->links = $links ?? new Links();
    }

    public function toObject(Process $process): stdClass
    {
        $result = ['description' => $this->description];

        if ($this->headers->items !== []) {
            $result['headers'] = $this->headers->toObject($process);
        }

        if ($this->content->items !== []) {
            $result['content'] = $this->content->toObject($process);
        }

        if ($this->links->items !== []) {
            $result['links'] = $this->links->toObject($process);
        }

        return (object) $result;
    }
}
