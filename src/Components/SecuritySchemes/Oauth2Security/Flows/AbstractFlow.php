<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows;

use EugeneErg\OpenApi\Extensions;
use stdClass;

abstract readonly class AbstractFlow
{
    public Extensions $extensions;

    public function __construct(
        public Scopes $scopes,
        public ?string $refreshUrl = null,
        ?Extensions $extensions = null,
    ) {
        $this->extensions = $extensions ?? new Extensions();
    }

    public function toObject(): stdClass
    {
        $result = $this->getUrls();

        if ($this->refreshUrl !== null) {
            $result['refreshUrl'] = $this->refreshUrl;
        }

        $result['scopes'] = $this->scopes->toObject();

        return (object) $this->extensions->appendTo($result);
    }

    /**
     * Обязательные url конкретного flow в порядке, заданном спецификацией.
     *
     * @return array<string, string>
     */
    abstract protected function getUrls(): array;
}
