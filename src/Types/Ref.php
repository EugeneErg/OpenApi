<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Types;

final readonly class Ref
{
    public function __construct(public string $value)
    {
    }
}
