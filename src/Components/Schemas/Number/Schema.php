<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Number;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractConditionSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchemas;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValues;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Access;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Bounds;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Discriminator;
use EugeneErg\OpenApi\Components\Schemas\Abstract\NumericRange;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Vocabularies;
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
        /**
         * Слово, проверяющее число, можно написать и не объявляя type: для значения
         * другого типа оно просто ничего не значит. Спецификация это разрешает,
         * и при чтении чужого документа такую проверку терять нельзя.
         */
        bool $declareType = true,
        ?Extensions $extensions = null,
    ) {
        self::assertRange('Number schema range', $this->minimum, $this->maximum);
        self::assertPositive('Number schema multipleOf', $this->multipleOf);

        parent::__construct(
            $declareType ? 'number' : null,
            $format instanceof Format ? $format->value : $format,
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
            $extensions,
        );
    }

    public function toObject(Process $process): stdClass
    {
        $result = Structure::vars(parent::toObject($process));

        $result = $this->appendRange($result, $process);

        return (object) $this->extensions->appendTo($result);
    }
}
