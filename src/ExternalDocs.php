<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi;

use stdClass;

final readonly class ExternalDocs
{
    public function __construct(
        public string $url,
        public ?string $description,
    ) {
    }

    /**
     * @return stdClass{url: string, description?: string}
     */
    public function toObject(): stdClass
    {
        $result = ['url' => $this->url];

        if ($this->description !== null) {
            $result['description'] = $this->description;
        }

        return (object) $result;
    }
}
