<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Exceptions;

use RuntimeException;

/**
 * Читаемый документ не соответствует спецификации или испорчен.
 */
final class InvalidDocumentOpenapiException extends RuntimeException implements OpenapiExceptionInterface
{
    public function __construct(string $message = 'Invalid document.')
    {
        parent::__construct($message);
    }
}
