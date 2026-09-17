<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\String;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractConditionSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchemas;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValues;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Access;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Bounds;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Discriminator;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Resource;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Xml;
use EugeneErg\OpenApi\Exceptions\InvalidSchemaOpenapiException;
use EugeneErg\OpenApi\Extensions;
use EugeneErg\OpenApi\ExternalDocs;
use EugeneErg\OpenApi\Process;
use EugeneErg\OpenApi\Serialization\Structure;
use stdClass;

final readonly class Schema extends AbstractConditionSchema
{
    use Bounds;

    /**
     * @param int<0, max> $minLength
     * @param null|int<0, max> $maxLength
     */
    public function __construct(
        ?string $title = null,
        ?string $description = null,
        bool $nullable = false,
        ?Access $access = null,
        bool $deprecated = false,
        ?ExternalDocs $externalDocs = null,
        ?Xml $xml = null,
        ?Value $default = null,
        ?AbstractSchemas $anyOf = null,
        ?AbstractSchemas $allOf = null,
        ?AbstractSchemas $oneOf = null,
        ?AbstractSchema $not = null,
        ?Value $example = null,
        public int $minLength = 0,
        public ?int $maxLength = null,
        public ?string $pattern = null,
        Format|string|null $format = null,
        public ?string $contentEncoding = null,
        public ?string $contentMediaType = null,
        public ?AbstractSchema $contentSchema = null,
        ?Discriminator $discriminator = null,
        ?AbstractValues $examples = null,
        ?Resource $resource = null,
        ?AbstractSchema $if = null,
        ?AbstractSchema $then = null,
        ?AbstractSchema $else = null,
        /**
         * A word that checks a string may be written without declaring type: for a value
         * of another type it simply means nothing. The specification allows that, and
         * reading somebody else's document must not lose such a check.
         */
        bool $declareType = true,
        ?Extensions $extensions = null,
    ) {
        self::assertRange('String schema length', $this->minLength, $this->maxLength);

        if ($contentSchema !== null && $contentMediaType === null) {
            throw new InvalidSchemaOpenapiException('"contentSchema" is meaningless without "contentMediaType".');
        }

        parent::__construct(
            type: $declareType ? 'string' : null,
            format: $format instanceof Format ? $format->value : $format,
            title: $title,
            description: $description,
            nullable: $nullable,
            access: $access,
            deprecated: $deprecated,
            externalDocs: $externalDocs,
            xml: $xml,
            default: $default,
            anyOf: $anyOf,
            allOf: $allOf,
            oneOf: $oneOf,
            not: $not,
            example: $example,
            discriminator: $discriminator,
            examples: $examples,
            if: $if,
            then: $then,
            else: $else,
            resource: $resource,
            extensions: $extensions,
        );
    }

    public function toObject(Process $process): stdClass
    {
        $result = Structure::vars(parent::toObject($process));

        if ($this->minLength !== 0 || $process->verbose) {
            $result['minLength'] = $this->minLength;
        }

        if ($this->maxLength !== null) {
            $result['maxLength'] = $this->maxLength;
        }

        if ($this->pattern !== null) {
            $result['pattern'] = $this->pattern;
        }

        if ($this->contentEncoding !== null) {
            $process->assertV31('"contentEncoding"');
            $result['contentEncoding'] = $this->contentEncoding;
        }

        if ($this->contentMediaType !== null) {
            $process->assertV31('"contentMediaType"');
            $result['contentMediaType'] = $this->contentMediaType;
        }

        if ($this->contentSchema !== null) {
            $process->assertV31('"contentSchema"');
            $result['contentSchema'] = self::nested($this->contentSchema, $process);
        }

        return (object) $this->extensions->appendTo($result);
    }
}
