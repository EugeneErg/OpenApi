<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\String;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractConditionSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchemas;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValue;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValues;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Access;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Bounds;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Discriminator;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Vocabularies;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Xml;
use EugeneErg\OpenApi\Exceptions\InvalidSchemaOpenapiException;
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
        public Format|string|null $format = null,
        public ?string $contentEncoding = null,
        public ?string $contentMediaType = null,
        public ?AbstractSchema $contentSchema = null,
        ?Discriminator $discriminator = null,
        ?AbstractValue $const = null,
        ?AbstractValues $examples = null,
        ?string $comment = null,
        ?AbstractSchemas $defs = null,
        ?string $id = null,
        ?string $anchor = null,
        ?string $dynamicAnchor = null,
        ?AbstractSchema $dynamicRef = null,
        ?Vocabularies $vocabulary = null,
        ?AbstractSchema $if = null,
        ?AbstractSchema $then = null,
        ?AbstractSchema $else = null,
    ) {
        self::assertRange('String schema length', $this->minLength, $this->maxLength);

        if ($contentSchema !== null && $contentMediaType === null) {
            throw new InvalidSchemaOpenapiException('"contentSchema" is meaningless without "contentMediaType".');
        }

        parent::__construct(
            'string',
            $title,
            $description,
            $nullable,
            $access,
            $deprecated,
            $externalDocs,
            $xml,
            $default,
            $anyOf,
            $allOf,
            $oneOf,
            $not,
            $example,
            $discriminator,
            $const,
            $examples,
            $comment,
            $defs,
            $id,
            $anchor,
            $dynamicAnchor,
            $dynamicRef,
            $vocabulary,
            $if,
            $then,
            $else,
        );
    }

    public function toObject(Process $process): stdClass
    {
        $result = Structure::vars(parent::toObject($process));

        if ($this->minLength !== 0) {
            $result['minLength'] = $this->minLength;
        }

        if ($this->maxLength !== null) {
            $result['maxLength'] = $this->maxLength;
        }

        if ($this->pattern !== null) {
            $result['pattern'] = $this->pattern;
        }

        if ($this->format !== null) {
            $result['format'] = $this->format instanceof Format ? $this->format->value : $this->format;
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

        return (object) $result;
    }
}
