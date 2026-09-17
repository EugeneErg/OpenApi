<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Integer;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractConditionSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchemas;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValues;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Access;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Bounds;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Discriminator;
use EugeneErg\OpenApi\Components\Schemas\Abstract\NumericRange;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Resource;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Xml;
use EugeneErg\OpenApi\Extensions;
use EugeneErg\OpenApi\ExternalDocs;
use EugeneErg\OpenApi\Process;
use EugeneErg\OpenApi\Serialization\Structure;
use stdClass;

final readonly class Schema extends AbstractConditionSchema
{
    use Bounds;
    use NumericRange;

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
        public float|int|null $minimum = null,
        public float|int|null $maximum = null,
        public bool $exclusiveMinimum = false,
        public bool $exclusiveMaximum = false,
        public float|int|null $multipleOf = null,
        Format|string|null $format = null,
        ?Discriminator $discriminator = null,
        ?AbstractValues $examples = null,
        ?Resource $resource = null,
        ?AbstractSchema $if = null,
        ?AbstractSchema $then = null,
        ?AbstractSchema $else = null,
        /**
         * A word that checks a number may be written without declaring type: for a value
         * of another type it simply means nothing. The specification allows that, and
         * reading somebody else's document must not lose such a check.
         */
        bool $declareType = true,
        ?Extensions $extensions = null,
    ) {
        self::assertRange('Integer schema range', $this->minimum, $this->maximum);
        self::assertPositive('Integer schema multipleOf', $this->multipleOf);

        parent::__construct(
            type: $declareType ? 'integer' : null,
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

        $result = $this->appendRange($result, $process);

        return (object) $this->extensions->appendTo($result);
    }
}
