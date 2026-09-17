<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\RequestBodies;

use EugeneErg\OpenApi\Exceptions\Place;
use EugeneErg\OpenApi\Extensions;
use EugeneErg\OpenApi\Process;
use stdClass;

final readonly class RequestBody
{
    public Extensions $extensions;

    public function __construct(
        public Contents $content,
        public bool $required = false,
        public ?string $description = null,
        ?Extensions $extensions = null,
    ) {
        $this->extensions = $extensions ?? new Extensions();
    }

    public function toObject(Process $process): stdClass
    {
        $result = [
            'content' => Place::in(fn (): stdClass => $this->content->toObject($process), 'content'),
        ];

        if ($this->required || $process->verbose) {
            $result['required'] = $this->required;
        }

        if ($this->description !== null) {
            $result['description'] = $this->description;
        }

        return (object) $this->extensions->appendTo($result);
    }
}
