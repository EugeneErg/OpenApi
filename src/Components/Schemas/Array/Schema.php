<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Array;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractConditionSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchemas;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValues;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Access;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Bounds;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Discriminator;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Vocabularies;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Xml;
use EugeneErg\OpenApi\Components\Schemas\Untyped\Schemas as UntypedSchemas;
use EugeneErg\OpenApi\Exceptions\InvalidSchemaOpenapiException;
use EugeneErg\OpenApi\Extensions;
use EugeneErg\OpenApi\ExternalDocs;
use EugeneErg\OpenApi\Process;
use EugeneErg\OpenApi\Serialization\Structure;
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
        ?string $format = null,
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
        public int $minItems = 0,
        public ?int $maxItems = null,
        public bool $uniqueItems = false,
        ?AbstractSchemas $prefixItems = null,
        public ?AbstractSchema $contains = null,
        public ?int $minContains = null,
        public ?int $maxContains = null,
        public AbstractSchema|bool|null $unevaluatedItems = null,
        ?Discriminator $discriminator = null,
        ?AbstractValues $examples = null,
        ?string $comment = null,
        /**
         * Схема может описывать форму, не объявляя type: спецификация это
         * разрешает, и при чтении чужого документа такой тип терять нельзя.
         */
        bool $declareType = true,
        ?AbstractSchemas $defs = null,
        ?string $id = null,
        ?string $anchor = null,
        ?string $dynamicAnchor = null,
        ?AbstractSchema $dynamicRef = null,
        ?Vocabularies $vocabulary = null,
        ?AbstractSchema $if = null,
        ?AbstractSchema $then = null,
        ?AbstractSchema $else = null,
        ?Extensions $extensions = null,
    ) {
        $this->prefixItems = $prefixItems ?? new UntypedSchemas();
        $this->prefixItems->assertListed('prefixItems');

        self::assertRange('Array schema size', $this->minItems, $this->maxItems);
        self::assertRange('Array schema contains count', $minContains, $maxContains);

        if (($minContains !== null || $maxContains !== null) && $contains === null) {
            throw new InvalidSchemaOpenapiException('"minContains" and "maxContains" are meaningless without "contains".');
        }

        parent::__construct(
            $declareType ? 'array' : null,
            $format,
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

        if ($this->items !== null) {
            $result['items'] = self::nested($this->items, $process);
        } elseif ($this->type !== null && !$process->version()->isV31()) {
            // 3.0: «items MUST be present if type is "array"» — без объявленного типа
            // требования нет, и слово о массиве живёт само по себе
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

        return (object) $this->extensions->appendTo($result);
    }
}
