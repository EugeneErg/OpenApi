<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Boolean;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractEnumSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchemas;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Access;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Vocabularies;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Xml;
use EugeneErg\OpenApi\Components\Schemas\Untyped\Values;
use EugeneErg\OpenApi\Extensions;
use EugeneErg\OpenApi\ExternalDocs;

/**
 * Единственное допустимое логическое значение: `const: true` в 3.1, `enum: [true]` в 3.0.
 *
 * Перечень из обоих значений ничего не ограничивает — это просто Boolean\Schema,
 * поэтому второго способа его записать нет.
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
        ?string $comment = null,
        ?AbstractSchemas $defs = null,
        ?string $id = null,
        ?string $anchor = null,
        ?string $dynamicAnchor = null,
        ?Vocabularies $vocabulary = null,
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
            comment: $comment,
            defs: $defs,
            id: $id,
            anchor: $anchor,
            dynamicAnchor: $dynamicAnchor,
            vocabulary: $vocabulary,
            extensions: $extensions,
        );
    }
}
