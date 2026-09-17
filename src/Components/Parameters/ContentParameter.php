<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Parameters;

use EugeneErg\OpenApi\Components\Parameters\Abstract\AbstractParameter;
use EugeneErg\OpenApi\Components\RequestBodies\Content;
use EugeneErg\OpenApi\Extensions;
use EugeneErg\OpenApi\Process;
use stdClass;

final readonly class ContentParameter extends AbstractParameter
{
    /**
     * @param string $mimeType the media type: in the document it becomes a key inside `content`
     */
    public function __construct(
        public string $mimeType,
        public Content $content,
        ?string $description = null,
        bool $required = false,
        bool $deprecated = false,
        ?Extensions $extensions = null,
    ) {
        parent::__construct(
            description: $description,
            required: $required,
            deprecated: $deprecated,
            extensions: $extensions,
        );
    }

    public function toObject(Process $process): stdClass
    {
        $result = [
            'content' => (object) [$this->mimeType => $this->content->toObject($process)],
        ];

        if ($this->description !== null) {
            $result['description'] = $this->description;
        }

        if ($this->required || $process->verbose) {
            $result['required'] = $this->required;
        }

        if ($this->deprecated || $process->verbose) {
            $result['deprecated'] = $this->deprecated;
        }

        return (object) $this->extensions->appendTo($result);
    }
}
