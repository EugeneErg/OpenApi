<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Integer;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractEnumSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchemas;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValues;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Access;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Vocabularies;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Xml;
use EugeneErg\OpenApi\Exceptions\InvalidSchemaOpenapiException;
use EugeneErg\OpenApi\Extensions;
use EugeneErg\OpenApi\ExternalDocs;
use EugeneErg\OpenApi\Process;
use EugeneErg\OpenApi\Serialization\Structure;
use stdClass;

use function is_int;
use function sprintf;

/**
 * Перечисление чисел. См. AbstractEnumSchema — почему здесь нет границ, multipleOf и example.
 */
final readonly class EnumSchema extends AbstractEnumSchema
{
    public function __construct(
        Integers $enums,
        ?string $title = null,
        ?string $description = null,
        bool $nullable = false,
        ?Access $access = null,
        bool $deprecated = false,
        ?ExternalDocs $externalDocs = null,
        ?Xml $xml = null,
        ?Value $default = null,
        Format|string|null $format = null,
        ?string $comment = null,
        ?AbstractSchemas $defs = null,
        ?string $id = null,
        ?string $anchor = null,
        ?string $dynamicAnchor = null,
        ?Vocabularies $vocabulary = null,
        ?Extensions $extensions = null,
    ) {
        parent::__construct(
            enums: $enums,
            format: $format instanceof Format ? $format->value : $format,
            type: 'integer',
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

    public function toObject(Process $process): stdClass
    {
        $result = Structure::vars(parent::toObject($process));

        return (object) $this->extensions->appendTo($result);
    }

    protected function assertValue(AbstractValues|bool|float|int|string $value): void
    {
        if (is_int($value) && $this->format === Format::Int32->value && ($value < -2147483648 || $value > 2147483647)) {
            throw new InvalidSchemaOpenapiException(sprintf('Enum value %d does not fit format "int32".', $value));
        }
    }
}
