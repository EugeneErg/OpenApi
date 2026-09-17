<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\String;

use DateTimeImmutable;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractEnumSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValues;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Access;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Resource;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Xml;
use EugeneErg\OpenApi\Exceptions\InvalidSchemaOpenapiException;
use EugeneErg\OpenApi\Extensions;
use EugeneErg\OpenApi\ExternalDocs;
use EugeneErg\OpenApi\Process;
use EugeneErg\OpenApi\Serialization\Structure;
use stdClass;

use function is_string;
use function sprintf;

/**
 * An enumeration of strings. AbstractEnumSchema says why minLength, pattern and example
 * are absent here.
 *
 * format stays: it is not only a check but an annotation as well — code generators pick a
 * type by it. The values of the known formats are checked right away.
 */
final readonly class EnumSchema extends AbstractEnumSchema
{
    public function __construct(
        Strings $enums,
        ?string $title = null,
        ?string $description = null,
        bool $nullable = false,
        ?Access $access = null,
        bool $deprecated = false,
        ?ExternalDocs $externalDocs = null,
        ?Xml $xml = null,
        ?Value $default = null,
        Format|string|null $format = null,
        public ?string $contentEncoding = null,
        public ?string $contentMediaType = null,
        public ?AbstractSchema $contentSchema = null,
        ?Resource $resource = null,
        ?Extensions $extensions = null,
    ) {
        if ($contentSchema !== null && $contentMediaType === null) {
            throw new InvalidSchemaOpenapiException('"contentSchema" is meaningless without "contentMediaType".');
        }

        parent::__construct(
            enums: $enums,
            format: $format instanceof Format ? $format->value : $format,
            type: 'string',
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

    public function toObject(Process $process): stdClass
    {
        $result = Structure::vars(parent::toObject($process));

        foreach (['contentEncoding' => $this->contentEncoding, 'contentMediaType' => $this->contentMediaType] as $keyword => $value) {
            if ($value !== null) {
                $process->assertV31(sprintf('"%s"', $keyword));
                $result[$keyword] = $value;
            }
        }

        if ($this->contentSchema !== null) {
            $process->assertV31('"contentSchema"');
            $result['contentSchema'] = self::nested($this->contentSchema, $process);
        }

        return (object) $this->extensions->appendTo($result);
    }

    protected function assertValue(AbstractValues|bool|float|int|string $value): void
    {
        if (!is_string($value)) {
            return;
        }

        $format = Format::tryFrom((string) $this->format);

        $valid = match ($format) {
            Format::Date => self::isDate($value),
            Format::DateTime => preg_match(
                '{^\d{4}-\d{2}-\d{2}[Tt]\d{2}:\d{2}:\d{2}(\.\d+)?([Zz]|[+-]\d{2}:\d{2})$}',
                $value,
            ) === 1 && self::isDate(substr($value, 0, 10)),
            Format::Email => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            Format::Uuid => preg_match('{^[0-9a-fA-F]{8}-([0-9a-fA-F]{4}-){3}[0-9a-fA-F]{12}$}', $value) === 1,
            Format::IPv4 => filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false,
            Format::IPv6 => filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false,
            Format::Byte => base64_decode($value, true) !== false,
            // the remaining formats are checked only where the check is unambiguous
            default => true,
        };

        if (!$valid) {
            throw new InvalidSchemaOpenapiException(sprintf(
                'Enum value "%s" does not match format "%s".',
                $value,
                $format->value ?? '',
            ));
        }
    }

    private static function isDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
