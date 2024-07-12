<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Info;

final readonly class Contacts
{
    public function __construct(
        public ?string $name = null,
        public ?string $url = null,
        public ?string $email = null,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->name === null && $this->url === null && $this->email === null;
    }

    /**
     * @return array{name?: string, url?: string, email?: string}
     */
    public function toArray(): array
    {
        $result = [];

        if ($this->name !== null) {
            $result['name'] = $this->name;
        }

        if ($this->url !== null) {
            $result['url'] = $this->url;
        }

        if ($this->email !== null) {
            $result['email'] = $this->email;
        }

        return $result;
    }
}
