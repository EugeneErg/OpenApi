<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Object;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractEnumSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Access;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Resource;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Xml;
use EugeneErg\OpenApi\Extensions;
use EugeneErg\OpenApi\ExternalDocs;

/**
 * An enumeration of objects. AbstractEnumSchema says why properties, required and example
 * are absent here.
 */
final readonly class EnumSchema extends AbstractEnumSchema
{
    public function __construct(
        Objects $enums,
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
            enums: $enums,
            format: $format,
            type: 'object',
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
