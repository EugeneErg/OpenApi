<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Untyped;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractEnumSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchemas;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Access;
use EugeneErg\OpenApi\Components\Schemas\Abstract\JsonValue;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Vocabularies;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Xml;
use EugeneErg\OpenApi\Exceptions\InvalidSchemaOpenapiException;
use EugeneErg\OpenApi\Extensions;
use EugeneErg\OpenApi\ExternalDocs;

use function count;
use function sprintf;

/**
 * Перечисление значений разных типов, например [1, "auto"].
 *
 * Если все значения одного типа, для них есть типизированная схема — ей и нужно
 * пользоваться, поэтому здесь такой набор отклоняется. null в перечень не пишется:
 * его добавляет nullable.
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
        ?string $comment = null,
        ?AbstractSchemas $defs = null,
        ?string $id = null,
        ?string $anchor = null,
        ?string $dynamicAnchor = null,
        ?Vocabularies $vocabulary = null,
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
