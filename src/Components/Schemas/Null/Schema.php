<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Null;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractConditionSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchemas;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValues;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Access;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Discriminator;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Vocabularies;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Xml;
use EugeneErg\OpenApi\Components\Schemas\Untyped\Value;
use EugeneErg\OpenApi\Exceptions\InvalidSchemaOpenapiException;
use EugeneErg\OpenApi\Extensions;
use EugeneErg\OpenApi\ExternalDocs;
use EugeneErg\OpenApi\Process;
use stdClass;

/**
 * Схема `{"type": "null"}`: допустимо единственное значение — null.
 *
 * Отличается от `nullable`: тот добавляет null к значениям своего типа, а здесь
 * других значений нет вовсе. Тип `null` появился в 3.1 вместе с JSON Schema 2020-12;
 * в 3.0 такого типа нет, и сборка это отклонит.
 *
 * Проверять в null нечего, поэтому своих ключевых слов у схемы нет — только общие.
 */
final readonly class Schema extends AbstractConditionSchema
{
    public function __construct(
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
        ?Extensions $extensions = null,
    ) {
        if ($nullable) {
            throw new InvalidSchemaOpenapiException('A null schema is already null: "nullable" adds nothing to it.');
        }

        parent::__construct(
            'null',
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
        $process->assertV31('The "null" type');

        return parent::toObject($process);
    }
}
