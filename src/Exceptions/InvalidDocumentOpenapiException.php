<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Exceptions;

use RuntimeException;

/**
 * The document being read does not match the specification, or is broken.
 */
final class InvalidDocumentOpenapiException extends RuntimeException implements OpenapiExceptionInterface
{
    use HasPlace;

    public function __construct(string $message = 'Invalid document.')
    {
        parent::__construct($message);
    }
}
