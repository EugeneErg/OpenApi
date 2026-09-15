<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows;

use stdClass;

abstract readonly class AbstractFlow
{
    public function __construct(
        public Scopes $scopes,
        public ?string $refreshUrl = null,
    ) {
    }

    public function toObject(): stdClass
    {
        $result = $this->getUrls();

        if ($this->refreshUrl !== null) {
            $result['refreshUrl'] = $this->refreshUrl;
        }

        $result['scopes'] = $this->scopes->toObject();

        return (object) $result;
    }

    /**
     * Обязательные url конкретного flow в порядке, заданном спецификацией.
     *
     * @return array<string, string>
     */
    abstract protected function getUrls(): array;
}
