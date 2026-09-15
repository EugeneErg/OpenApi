<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Serialization;

use EugeneErg\OpenApi\Exceptions\InvalidDocumentOpenapiException;
use JsonException;
use stdClass;

use function sprintf;

final readonly class JsonDecoder implements DecoderInterface
{
    public function decode(string $content): stdClass
    {
        try {
            $result = json_decode($content, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidDocumentOpenapiException(
                sprintf('Document is not valid JSON: %s.', $exception->getMessage()),
            );
        }

        if (!$result instanceof stdClass) {
            throw new InvalidDocumentOpenapiException(
                sprintf('Document must be an object at the top level, got %s.', get_debug_type($result)),
            );
        }

        return $result;
    }
}
