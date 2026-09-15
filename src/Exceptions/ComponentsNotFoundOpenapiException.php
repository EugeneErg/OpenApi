<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Exceptions;

use LogicException;

final class ComponentsNotFoundOpenapiException extends LogicException implements OpenapiExceptionInterface
{
    public function __construct(string $message = 'Components not found.')
    {
        parent::__construct($message);
    }
}
