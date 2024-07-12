<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\RequestBodies;

use EugeneErg\OpenApi\Components\Headers;
use EugeneErg\OpenApi\Process;
use stdClass;

final readonly class Encoding
{
    public Headers $headers;

    public function __construct(
        public ?string $contentType = null,
        public bool $explode = true,
        public bool $allowReserved = false,
        public EncodingStyle $style = EncodingStyle::Form,
        ?Headers $headers = null,
    ) {
        $this->headers = $headers ?? new Headers();
    }

    public function toObject(Process $process): stdClass
    {
        $result = [
            'explode' => $this->explode,
            'allowReserved' => $this->allowReserved,
            'style' => $this->style->value,
        ];

        if ($this->contentType !== null) {
            $result['contentType'] = $this->contentType;
        }

        if ($this->headers->items !== []) {
            $result['headers'] = $this->headers->toObject($process);
        }

        return (object) $result;
    }
}
