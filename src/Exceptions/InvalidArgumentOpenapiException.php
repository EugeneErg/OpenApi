<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Exceptions;

use InvalidArgumentException;

final class InvalidArgumentOpenapiException extends InvalidArgumentException implements OpenapiExceptionInterface
{
    public function __construct(string $message = 'Invalid argument.')
    {
        parent::__construct($message);
    }
}
