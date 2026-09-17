<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Abstract;

use EugeneErg\OpenApi\Components\Schemas\Untyped\Schemas;
use EugeneErg\OpenApi\Exceptions\ComponentsNotFoundOpenapiException;
use EugeneErg\OpenApi\Extensions;
use EugeneErg\OpenApi\Process;
use stdClass;

use function sprintf;

/**
 * A Discriminator Object. Applicable only together with oneOf, anyOf or allOf.
 *
 * The keys of $mapping are the values of the discriminating property; the values are the
 * schemas, which become $ref strings when the document is built.
 */
final readonly class Discriminator
{
    public AbstractSchemas $mapping;

    public Extensions $extensions;

    public function __construct(
        public string $propertyName,
        ?AbstractSchemas $mapping = null,
        ?Extensions $extensions = null,
    ) {
        $this->extensions = $extensions ?? new Extensions();
        $this->mapping = $mapping ?? new Schemas();
        $this->mapping->assertNamed('discriminator.mapping');
    }

    public function toObject(Process $process): stdClass
    {
        $result = ['propertyName' => $this->propertyName];

        if ($this->mapping->items !== []) {
            $mapping = [];

            foreach ($this->mapping->items as $key => $schema) {
                $ref = $process->findSchema($schema);

                if ($ref === null) {
                    throw new ComponentsNotFoundOpenapiException(sprintf(
                        'Discriminator mapping "%s" must point to a schema registered in components.schemas.',
                        (string) $key,
                    ));
                }

                $mapping[$key] = $ref->{'$ref'};
            }

            $result['mapping'] = (object) $mapping;
        }

        if ($this->extensions->items !== []) {
            // a Discriminator Object gained extensions only in 3.1
            $process->assertV31('Specification extensions on a discriminator');
        }

        return (object) $this->extensions->appendTo($result);
    }
}
