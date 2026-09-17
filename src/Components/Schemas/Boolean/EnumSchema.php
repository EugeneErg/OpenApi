<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Boolean;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractEnumSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Access;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Resource;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Xml;
use EugeneErg\OpenApi\Components\Schemas\Untyped\Values;
use EugeneErg\OpenApi\Extensions;
use EugeneErg\OpenApi\ExternalDocs;

/**
 * The only admissible boolean value: `const: true` in 3.1, `enum: [true]` in 3.0.
 *
 * A list of both values restricts nothing — that is simply Boolean\Schema — so there is
 * no second way to write it.
 */
final readonly class EnumSchema extends AbstractEnumSchema
{
    public function __construct(
        public bool $value,
        ?string $title = null,
        ?string $description = null,
        ?string $format = null,
        bool $nullable = false,
        ?Access $access = null,
        bool $deprecated = false,
        ?ExternalDocs $externalDocs = null,
        ?Xml $xml = null,
        ?Value $default = null,
        ?Resource $resource = null,
        ?Extensions $extensions = null,
    ) {
        parent::__construct(
            enums: new Values($value),
            format: $format,
            type: 'boolean',
            title: $title,
            description: $description,
            nullable: $nullable,
            access: $access,
            deprecated: $deprecated,
            externalDocs: $externalDocs,
            xml: $xml,
            default: $default,
            resource: $resource,
            extensions: $extensions,
        );
    }
}
