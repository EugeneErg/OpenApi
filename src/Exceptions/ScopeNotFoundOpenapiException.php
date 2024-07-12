<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Exceptions;

use RuntimeException;

final class ScopeNotFoundOpenapiException extends RuntimeException implements OpenapiExceptionInterface
{
    public function __construct(string $message = 'Scope not found.')
    {
        parent::__construct($message);
    }
}
