<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\SecuritySchemes;

/**
 * Схема mutualTLS, появившаяся в OpenAPI 3.1.
 */
final readonly class MutualTlsSecurityScheme extends AbstractSecurityScheme
{
    public function __construct(?string $description = null)
    {
        parent::__construct('mutualTLS', $description);
    }
}
