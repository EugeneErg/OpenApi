<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Exceptions;

use LogicException;

final class ComponentsNotFoundOpenapiException extends LogicException implements OpenapiExceptionInterface
{
    use HasPlace;

    public function __construct(string $message = 'Components not found.')
    {
        parent::__construct($message);
    }
}
