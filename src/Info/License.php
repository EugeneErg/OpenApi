<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Info;

final readonly class License
{
    public function __construct(
        public string $name,
        public ?string $url = null,
    ) {
    }

    /**
     * @return array{name: string, url?: string}
     */
    public function toArray(): array
    {
        $result = [
            'name' => $this->name,
        ];

        if ($this->url !== null) {
            $result['url'] = $this->url;
        }

        return $result;
    }
}
