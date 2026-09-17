<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Abstract;

use EugeneErg\OpenApi\Components\Schemas\Untyped\Schemas;
use EugeneErg\OpenApi\Exceptions\InvalidSchemaOpenapiException;
use EugeneErg\OpenApi\Exceptions\Place;
use EugeneErg\OpenApi\Extensions;
use EugeneErg\OpenApi\ExternalDocs;
use EugeneErg\OpenApi\Process;
use EugeneErg\OpenApi\Serialization\Structure;
use stdClass;

use function sprintf;

abstract readonly class AbstractConditionSchema extends AbstractSchema
{
    public AbstractSchemas $anyOf;
    public AbstractSchemas $allOf;
    public AbstractSchemas $oneOf;

    public function __construct(
        ?string $type,
        ?string $format = null,
        ?string $title = null,
        ?string $description = null,
        bool $nullable = false,
        ?Access $access = null,
        bool $deprecated = false,
        ?ExternalDocs $externalDocs = null,
        ?Xml $xml = null,
        ?AbstractValue $default = null,
        ?AbstractSchemas $anyOf = null,
        ?AbstractSchemas $allOf = null,
        ?AbstractSchemas $oneOf = null,
        public ?AbstractSchema $not = null,
        public ?AbstractValue $example = null,
        public ?Discriminator $discriminator = null,
        ?AbstractValues $examples = null,
        ?Resource $resource = null,
        public ?AbstractSchema $if = null,
        public ?AbstractSchema $then = null,
        public ?AbstractSchema $else = null,
        ?Extensions $extensions = null,
    ) {
        parent::__construct(
            type: $type,
            format: $format,
            title: $title,
            description: $description,
            nullable: $nullable,
            access: $access,
            deprecated: $deprecated,
            externalDocs: $externalDocs,
            xml: $xml,
            default: $default,
            examples: $examples,
            resource: $resource,
            extensions: $extensions,
        );
        $this->anyOf = $anyOf ?? new Schemas();
        $this->allOf = $allOf ?? new Schemas();
        $this->oneOf = $oneOf ?? new Schemas();

        foreach (['anyOf' => $this->anyOf, 'allOf' => $this->allOf, 'oneOf' => $this->oneOf] as $keyword => $schemas) {
            $schemas->assertListed($keyword);
        }

        if (($then !== null || $else !== null) && $if === null) {
            throw new InvalidSchemaOpenapiException('"then" and "else" are meaningless without "if".');
        }

        if ($discriminator !== null
            && $this->anyOf->items === []
            && $this->allOf->items === []
            && $this->oneOf->items === []
        ) {
            throw new InvalidSchemaOpenapiException(
                'Discriminator is only meaningful together with oneOf, anyOf or allOf.',
            );
        }
    }

    public function toObject(Process $process): stdClass
    {
        $result = Structure::vars(parent::toObject($process));

        foreach (['anyOf' => $this->anyOf, 'allOf' => $this->allOf, 'oneOf' => $this->oneOf] as $keyword => $schemas) {
            if ($schemas->items !== []) {
                $result[$keyword] = Place::in(static fn (): array => $schemas->toArray($process), $keyword);
            }
        }

        if ($this->not !== null) {
            $result['not'] = Place::in(fn (): stdClass => self::nested($this->not, $process), 'not');
        }

        foreach (['if' => $this->if, 'then' => $this->then, 'else' => $this->else] as $keyword => $schema) {
            if ($schema !== null) {
                $process->assertV31(sprintf('"%s"', $keyword));
                $result[$keyword] = self::nested($schema, $process);
            }
        }

        if ($this->discriminator !== null) {
            $result['discriminator'] = $this->discriminator->toObject($process);
        }

        if ($this->example !== null) {
            $result['example'] = $this->example->toNative($process);
        }

        return (object) $this->extensions->appendTo($result);
    }
}
