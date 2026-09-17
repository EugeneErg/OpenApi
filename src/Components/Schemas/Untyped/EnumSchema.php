<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Untyped;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractEnumSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Access;
use EugeneErg\OpenApi\Components\Schemas\Abstract\JsonValue;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Resource;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Xml;
use EugeneErg\OpenApi\Exceptions\InvalidSchemaOpenapiException;
use EugeneErg\OpenApi\Extensions;
use EugeneErg\OpenApi\ExternalDocs;

use function count;
use function sprintf;

/**
 * An enumeration of values of different types, for example [1, "auto"].
 *
 * When every value is of one type there is a typed schema for them, and that is the one
 * to use, so such a set is rejected here. null is not written into the list: nullable
 * adds it.
 */
final readonly class EnumSchema extends AbstractEnumSchema
{
    public function __construct(
        Values $enums,
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
        $kinds = [];

        foreach ($enums->items as $item) {
            $kinds[JsonValue::kind($item)] = true;
        }

        if (count($kinds) < 2) {
            throw new InvalidSchemaOpenapiException(sprintf(
                'All enum values are of type %s: use the typed EnumSchema of that type.',
                (string) array_key_first($kinds),
            ));
        }

        parent::__construct(
            enums: $enums,
            format: $format,
            type: null,
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
