<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Integer;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractConditionSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchemas;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValue;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValues;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Access;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Bounds;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Discriminator;
use EugeneErg\OpenApi\Components\Schemas\Abstract\NumericRange;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Vocabularies;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Xml;
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
        public ?int $minimum = null,
        public ?int $maximum = null,
        public bool $exclusiveMinimum = false,
        public bool $exclusiveMaximum = false,
        public ?int $multipleOf = null,
        public ?Format $format = null,
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
        self::assertRange('Integer schema range', $this->minimum, $this->maximum);
        self::assertPositive('Integer schema multipleOf', $this->multipleOf);

        parent::__construct(
            'integer',
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

        $result = $this->appendRange($result, $process);

        if ($this->format !== null) {
            $result['format'] = $this->format->value;
        }

        return (object) $result;
    }
}
