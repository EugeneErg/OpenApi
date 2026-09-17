<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Exceptions;

use RuntimeException;

final class SecuritySchemeNotFoundOpenapiException extends RuntimeException implements OpenapiExceptionInterface
{
    use HasPlace;

    public function __construct(string $message = 'Security scheme not found.')
    {
        parent::__construct($message);
    }
}
