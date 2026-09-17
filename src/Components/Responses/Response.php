<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Responses;

use EugeneErg\OpenApi\Components\Headers;
use EugeneErg\OpenApi\Components\Links;
use EugeneErg\OpenApi\Components\RequestBodies\Contents;
use EugeneErg\OpenApi\Exceptions\Place;
use EugeneErg\OpenApi\Extensions;
use EugeneErg\OpenApi\Process;
use stdClass;

final readonly class Response
{
    public Extensions $extensions;

    public Headers $headers;
    public Contents $content;
    public Links $links;

    public function __construct(
        public string $description,
        ?Headers $headers = null,
        ?Contents $content = null,
        ?Links $links = null,
        ?Extensions $extensions = null,
    ) {
        $this->extensions = $extensions ?? new Extensions();

        $this->headers = $headers ?? new Headers();
        $this->content = $content ?? new Contents();
        $this->links = $links ?? new Links();
    }

    public function toObject(Process $process): stdClass
    {
        $result = ['description' => $this->description];

        if ($this->headers->items !== []) {
            $result['headers'] = Place::in(fn (): stdClass => $this->headers->toObject($process), 'headers');
        }

        if ($this->content->items !== []) {
            $result['content'] = Place::in(fn (): stdClass => $this->content->toObject($process), 'content');
        }

        if ($this->links->items !== []) {
            $result['links'] = Place::in(fn (): stdClass => $this->links->toObject($process), 'links');
        }

        return (object) $this->extensions->appendTo($result);
    }
}
