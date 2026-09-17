<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Info;

use EugeneErg\OpenApi\Extensions;

final readonly class Contact
{
    public Extensions $extensions;

    public function __construct(
        public ?string $name = null,
        public ?string $url = null,
        public ?string $email = null,
        ?Extensions $extensions = null,
    ) {
        $this->extensions = $extensions ?? new Extensions();
    }

    public function isEmpty(): bool
    {
        return $this->name === null
            && $this->url === null
            && $this->email === null
            && $this->extensions->items === [];
    }

    /**
     * @return array<array-key, mixed>
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

        return $this->extensions->appendTo($result);
    }
}
