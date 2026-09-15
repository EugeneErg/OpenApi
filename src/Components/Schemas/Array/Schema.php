<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Array;

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
use EugeneErg\OpenApi\Components\Schemas\Untyped\Schemas as UntypedSchemas;
use EugeneErg\OpenApi\Exceptions\InvalidSchemaOpenapiException;
use EugeneErg\OpenApi\ExternalDocs;
use EugeneErg\OpenApi\Process;
use stdClass;

final readonly class Schema extends AbstractConditionSchema
{
    use Bounds;

    public AbstractSchemas $prefixItems;

    /**
     * @param int<0, max> $minItems
     * @param null|int<0, max> $maxItems
     */
    public function __construct(
        public ?AbstractSchema $items = null,
        ?string $title = null,
        ?string $description = null,
        bool $nullable = false,
        ?Access $access = null,
        bool $deprecated = false,
        ?ExternalDocs $externalDocs = null,
        ?Xml $xml = null,
        ?Value $default = null,
        ?Schemas $anyOf = null,
        ?Schemas $allOf = null,
        ?Schemas $oneOf = null,
        null|EnumSchema|self $not = null,
        ?Value $example = null,
        public int $minItems = 0,
        public ?int $maxItems = null,
        public bool $uniqueItems = false,
        ?AbstractSchemas $prefixItems = null,
        public ?AbstractSchema $contains = null,
        public ?int $minContains = null,
        public ?int $maxContains = null,
        public null|AbstractSchema|bool $unevaluatedItems = null,
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
        $this->prefixItems = $prefixItems ?? new UntypedSchemas();

        self::assertRange('Array schema size', $this->minItems, $this->maxItems);
        self::assertRange('Array schema contains count', $minContains, $maxContains);

        if (($minContains !== null || $maxContains !== null) && $contains === null) {
            throw new InvalidSchemaOpenapiException('"minContains" and "maxContains" are meaningless without "contains".');
        }

        parent::__construct(
            'array',
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
        $result = get_object_vars(parent::toObject($process));

        if ($this->items !== null) {
            $result['items'] = self::nested($this->items, $process);
        } elseif (!$process->version()->isV31()) {
            throw new InvalidSchemaOpenapiException(
                'An array schema must declare "items" in OpenAPI 3.0; it is optional only since 3.1.',
            );
        }

        if ($this->minItems > 0) {
            $result['minItems'] = $this->minItems;
        }

        if ($this->maxItems !== null) {
            $result['maxItems'] = $this->maxItems;
        }

        if ($this->uniqueItems) {
            $result['uniqueItems'] = $this->uniqueItems;
        }

        if ($this->prefixItems->items !== []) {
            $process->assertV31('"prefixItems"');
            $result['prefixItems'] = $this->prefixItems->toArray($process);
        }

        if ($this->contains !== null) {
            $process->assertV31('"contains"');
            $result['contains'] = self::nested($this->contains, $process);
        }

        if ($this->minContains !== null) {
            $result['minContains'] = $this->minContains;
        }

        if ($this->maxContains !== null) {
            $result['maxContains'] = $this->maxContains;
        }

        if ($this->unevaluatedItems !== null) {
            $process->assertV31('"unevaluatedItems"');
            $result['unevaluatedItems'] = $this->unevaluatedItems instanceof AbstractSchema
                ? self::nested($this->unevaluatedItems, $process)
                : $this->unevaluatedItems;
        }

        return (object) $result;
    }
}
