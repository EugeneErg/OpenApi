<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Serialization;

use JsonException;
use stdClass;

final readonly class JsonEncoder implements EncoderInterface
{
    public const int DEFAULT_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    public function __construct(private int $flags = self::DEFAULT_FLAGS)
    {
    }

    /**
     * @throws JsonException
     */
    public function encode(stdClass $document): string
    {
        return json_encode($document, $this->flags | JSON_THROW_ON_ERROR) . "\n";
    }
}
