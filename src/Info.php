<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi;

use EugeneErg\OpenApi\Info\Contacts;
use EugeneErg\OpenApi\Info\License;
use stdClass;

final readonly class Info
{
    public Contacts $contacts;

    public function __construct(
        public string $title,
        public string $version,
        public ?string $description = null,
        public ?string $termsOfService = null,
        ?Contacts $contacts = null,
        public ?License $license = null,
    ) {
        $this->contacts = $contacts ?? new Contacts();
    }

    public function toObject(): stdClass
    {
        $result = [
            'title' => $this->title,
            'version' => $this->version,
        ];

        if ($this->description !== null) {
            $result['description'] = $this->description;
        }

        if (!$this->contacts->isEmpty()) {
            $result['contacts'] = $this->contacts->toArray();
        }

        if ($this->license !== null) {
            $result['license'] = $this->license->toArray();
        }

        if ($this->termsOfService !== null) {
            $result['termsOfService'] = $this->termsOfService;
        }

        return (object) $result;
    }
}
