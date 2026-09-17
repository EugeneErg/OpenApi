<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Null;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractConditionSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchemas;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValues;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Access;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Discriminator;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Resource;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Xml;
use EugeneErg\OpenApi\Components\Schemas\Untyped\Value;
use EugeneErg\OpenApi\Exceptions\InvalidSchemaOpenapiException;
use EugeneErg\OpenApi\Extensions;
use EugeneErg\OpenApi\ExternalDocs;
use EugeneErg\OpenApi\Process;
use stdClass;

/**
 * The schema `{"type": "null"}`: null is the only admissible value.
 *
 * It differs from `nullable`: that one adds null to the values of its own type, while
 * here there are no other values at all. The `null` type appeared in 3.1 together with
 * JSON Schema 2020-12; 3.0 has no such type, and the build rejects it.
 *
 * There is nothing to check in null, so the schema has no keywords of its own — only the
 * common ones.
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
        ?Resource $resource = null,
        ?AbstractSchema $if = null,
        ?AbstractSchema $then = null,
        ?AbstractSchema $else = null,
        ?Extensions $extensions = null,
    ) {
        if ($nullable) {
            throw new InvalidSchemaOpenapiException('A null schema is already null: "nullable" adds nothing to it.');
        }

        parent::__construct(
            type: 'null',
            format: $format,
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
        $process->assertV31('The "null" type');

        return parent::toObject($process);
    }
}
