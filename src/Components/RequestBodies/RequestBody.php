<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\RequestBodies;

use EugeneErg\OpenApi\Process;
use stdClass;

final readonly class RequestBody
{
    public function __construct(
        public Contents $content,
        public bool $required = false,
        public ?string $description = null,
    ) {
    }

    /**
     * @return stdClass{
     *     content: array{},
     *     required: bool,
     *     description?: string,
     * }
     */
    public function toObject(Process $process): stdClass
    {
        $result = [
            'content' => $this->content->toObject($process),
            'required' => $this->required,
        ];

        if ($this->description !== null) {
            $result['description'] = $this->description;
        }

        return (object) $result;
    }
}
