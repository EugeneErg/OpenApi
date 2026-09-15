<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Exceptions;

use LogicException;

final class OperationNotFoundOpenapiException extends LogicException implements OpenapiExceptionInterface
{
    public function __construct(string $message = 'Operation not found.')
    {
        parent::__construct($message);
    }
}
