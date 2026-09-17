<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\SecuritySchemes;

use EugeneErg\OpenApi\Extensions;

/**
 * Схема mutualTLS, появившаяся в OpenAPI 3.1.
 */
final readonly class MutualTlsSecurityScheme extends AbstractSecurityScheme
{
    public function __construct(
        ?string $description = null,
        ?Extensions $extensions = null,
    ) {
        parent::__construct('mutualTLS', $description, $extensions);
    }
}
