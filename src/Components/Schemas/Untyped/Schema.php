<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Untyped;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractConditionSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchemas;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValues;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Access;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Discriminator;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Resource;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Xml;
use EugeneErg\OpenApi\Extensions;
use EugeneErg\OpenApi\ExternalDocs;
use EugeneErg\OpenApi\Process;
use EugeneErg\OpenApi\Serialization\Structure;
use stdClass;

use function count;
use function is_array;

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
        parent::__construct(
            type: null,
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
        $result = parent::toObject($process);

        // a schema that is one allOf with a single member is that member itself
        if (array_keys(Structure::vars($result)) === ['allOf'] && is_array($result->allOf) && count($result->allOf) === 1) {
            foreach ($result->allOf as $single) {
                if ($single instanceof stdClass) {
                    return $single;
                }
            }
        }

        return (object) $this->extensions->appendTo($result);
    }
}
