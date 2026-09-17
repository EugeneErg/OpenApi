<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\RequestBodies;

use EugeneErg\OpenApi\Components\Headers;
use EugeneErg\OpenApi\Extensions;
use EugeneErg\OpenApi\Process;
use stdClass;

final readonly class Encoding
{
    public Extensions $extensions;

    public Headers $headers;

    /**
     * null — не задано. Это не то же самое, что значение по умолчанию: в 3.1 для
     * multipart явно заданные style, explode или allowReserved отменяют обработку
     * части по contentType. Поэтому печатается только то, что задано.
     */
    public function __construct(
        public ?string $contentType = null,
        public ?bool $explode = null,
        public ?bool $allowReserved = null,
        public ?EncodingStyle $style = null,
        ?Headers $headers = null,
        ?Extensions $extensions = null,
    ) {
        $this->extensions = $extensions ?? new Extensions();

        $this->headers = $headers ?? new Headers();
    }

    public function toObject(Process $process): stdClass
    {
        $result = [];

        if ($this->contentType !== null) {
            $result['contentType'] = $this->contentType;
        }

        if ($this->headers->items !== []) {
            $result['headers'] = $this->headers->toObject($process);
        }

        if ($this->style !== null) {
            $result['style'] = $this->style->value;
        }

        if ($this->explode !== null) {
            $result['explode'] = $this->explode;
        }

        if ($this->allowReserved !== null) {
            $result['allowReserved'] = $this->allowReserved;
        }

        return (object) $this->extensions->appendTo($result);
    }
}
