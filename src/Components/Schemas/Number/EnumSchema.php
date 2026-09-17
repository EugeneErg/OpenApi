<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Number;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractEnumSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValues;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Access;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Resource;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Xml;
use EugeneErg\OpenApi\Exceptions\InvalidSchemaOpenapiException;
use EugeneErg\OpenApi\Extensions;
use EugeneErg\OpenApi\ExternalDocs;

use function is_float;
use function is_int;
use function sprintf;

/**
 * An enumeration of numbers. AbstractEnumSchema says why the bounds, multipleOf and
 * example are absent here.
 */
final readonly class EnumSchema extends AbstractEnumSchema
{
    public function __construct(
        Numbers $enums,
        ?string $title = null,
        ?string $description = null,
        bool $nullable = false,
        ?Access $access = null,
        bool $deprecated = false,
        ?ExternalDocs $externalDocs = null,
        ?Xml $xml = null,
        ?Value $default = null,
        Format|string|null $format = null,
        ?Resource $resource = null,
        ?Extensions $extensions = null,
    ) {
        parent::__construct(
            enums: $enums,
            format: $format instanceof Format ? $format->value : $format,
            type: 'number',
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

    protected function assertValue(AbstractValues|bool|float|int|string $value): void
    {
        if (!is_float($value) && !is_int($value)) {
            return;
        }

        if ($this->format === Format::Float->value && is_finite((float) $value) && abs($value) > 3.4028234663852886E+38) {
            throw new InvalidSchemaOpenapiException(sprintf('Enum value %s does not fit format "float".', var_export($value, true)));
        }
    }
}
