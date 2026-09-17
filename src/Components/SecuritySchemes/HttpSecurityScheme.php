<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\SecuritySchemes;

use EugeneErg\OpenApi\Exceptions\InvalidArgumentOpenapiException;
use EugeneErg\OpenApi\Extensions;
use stdClass;

use function in_array;
use function sprintf;

/**
 * HTTP authentication by any scheme from the IANA registry except basic and bearer:
 * Digest, Negotiate, HOBA, Mutual, SCRAM-SHA-256, vapid and so on.
 *
 * basic and bearer have classes of their own — a second way to write them is not needed,
 * and bearerFormat belongs to bearer alone.
 *
 * By RFC 7235 a scheme name is case-insensitive, so it is written as given.
 */
final readonly class HttpSecurityScheme extends AbstractSecurityScheme
{
    public function __construct(
        public string $scheme,
        ?string $description = null,
        ?Extensions $extensions = null,
    ) {
        // a token from RFC 7230: no spaces and no separators
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

        parent::__construct(
            type: 'http',
            description: $description,
            extensions: $extensions,
        );
    }

    public function toObject(): stdClass
    {
        $result = parent::toObject();
        $result->scheme = $this->scheme;

        return (object) $this->extensions->appendTo($result);
    }
}
