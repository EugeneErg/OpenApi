<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi;

use EugeneErg\OpenApi\Info\Contact;
use EugeneErg\OpenApi\Info\License;
use stdClass;

final readonly class Info
{
    public Contact $contact;

    public function __construct(
        public string $title,
        public string $version,
        public ?string $summary = null,
        public ?string $description = null,
        public ?string $termsOfService = null,
        ?Contact $contact = null,
        public ?License $license = null,
    ) {
        $this->contact = $contact ?? new Contact();
    }

    public function toObject(): stdClass
    {
        $result = [
            'title' => $this->title,
            'version' => $this->version,
        ];

        if ($this->summary !== null) {
            $result['summary'] = $this->summary;
        }

        if ($this->description !== null) {
            $result['description'] = $this->description;
        }

        if (!$this->contact->isEmpty()) {
            $result['contact'] = (object) $this->contact->toArray();
        }

        if ($this->license !== null) {
            $result['license'] = (object) $this->license->toArray();
        }

        if ($this->termsOfService !== null) {
            $result['termsOfService'] = $this->termsOfService;
        }

        return (object) $result;
    }
}
