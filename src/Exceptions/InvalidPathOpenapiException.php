<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Exceptions;

use InvalidArgumentException;

final class InvalidPathOpenapiException extends InvalidArgumentException implements OpenapiExceptionInterface
{
    public function __construct(string $message = 'Invalid path.')
    {
        parent::__construct($message);
    }
}
