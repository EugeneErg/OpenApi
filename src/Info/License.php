<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Info;

use EugeneErg\OpenApi\Exceptions\InvalidArgumentOpenapiException;
use EugeneErg\OpenApi\Extensions;

final readonly class License
{
    public Extensions $extensions;

    /**
     * @param null|string $identifier SPDX-идентификатор, доступен начиная с OpenAPI 3.1
     */
    public function __construct(
        public string $name,
        public ?string $url = null,
        public ?string $identifier = null,
        ?Extensions $extensions = null,
    ) {
        $this->extensions = $extensions ?? new Extensions();

        if ($url !== null && $identifier !== null) {
            throw new InvalidArgumentOpenapiException(
                'License cannot have both url and identifier: they are mutually exclusive.',
            );
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        $result = ['name' => $this->name];

        if ($this->identifier !== null) {
            $result['identifier'] = $this->identifier;
        }

        if ($this->url !== null) {
            $result['url'] = $this->url;
        }

        return $this->extensions->appendTo($result);
    }
}
