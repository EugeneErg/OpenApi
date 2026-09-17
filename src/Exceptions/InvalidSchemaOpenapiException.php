<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Exceptions;

use InvalidArgumentException;

final class InvalidSchemaOpenapiException extends InvalidArgumentException implements OpenapiExceptionInterface
{
    use HasPlace;

    public function __construct(string $message = 'Invalid schema.')
    {
        parent::__construct($message);
    }
}
