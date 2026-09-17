<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\SecuritySchemes;

use EugeneErg\OpenApi\Exceptions\InvalidArgumentOpenapiException;
use EugeneErg\OpenApi\Extensions;
use stdClass;

use function in_array;
use function sprintf;

/**
 * HTTP-аутентификация по любой схеме из реестра IANA, кроме basic и bearer:
 * Digest, Negotiate, HOBA, Mutual, SCRAM-SHA-256, vapid и т. п.
 *
 * Для basic и bearer есть отдельные классы — второй способ записать их
 * не нужен, а bearerFormat бывает только у bearer.
 *
 * Имя схемы по RFC 7235 регистронезависимо, поэтому пишется как задано.
 */
final readonly class HttpSecurityScheme extends AbstractSecurityScheme
{
    public function __construct(
        public string $scheme,
        ?string $description = null,
        ?Extensions $extensions = null,
    ) {
        // token из RFC 7230: без пробелов и разделителей
        if (preg_match('{^[!#$%&\'*+.^_`|~0-9A-Za-z-]+$}', $scheme) !== 1) {
            throw new InvalidArgumentOpenapiException(sprintf(
                'HTTP authentication scheme must be an RFC 7230 token, got "%s".',
                $scheme,
            ));
        }

        $dedicated = [
            'basic' => BasicHttpSecurityScheme::class,
            'bearer' => BearerHttpSecurityScheme::class,
        ];
        $lower = strtolower($scheme);

        if (in_array($lower, ['basic', 'bearer'], true)) {
            throw new InvalidArgumentOpenapiException(sprintf(
                'Use %s for the "%s" HTTP scheme.',
                $dedicated[$lower],
                $scheme,
            ));
        }

        parent::__construct('http', $description, $extensions);
    }

    public function toObject(): stdClass
    {
        $result = parent::toObject();
        $result->scheme = $this->scheme;

        return (object) $this->extensions->appendTo($result);
    }
}
