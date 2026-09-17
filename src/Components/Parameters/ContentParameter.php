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
     * @param string $mimeType медиатип: в документе он становится ключом внутри `content`
     */
    public function __construct(
        public string $mimeType,
        public Content $content,
        ?string $description = null,
        bool $required = false,
        bool $deprecated = false,
        ?Extensions $extensions = null,
    ) {
        parent::__construct($description, $required, $deprecated, $extensions);
    }

    public function toObject(Process $process): stdClass
    {
        $result = [
            'content' => (object) [$this->mimeType => $this->content->toObject($process)],
        ];

        if ($this->description !== null) {
            $result['description'] = $this->description;
        }

        if ($this->required) {
            $result['required'] = $this->required;
        }

        if ($this->deprecated) {
            $result['deprecated'] = $this->deprecated;
        }

        return (object) $this->extensions->appendTo($result);
    }
}
