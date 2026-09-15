<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Abstract;

use EugeneErg\OpenApi\Components\Schemas\Untyped\Schemas;
use EugeneErg\OpenApi\Exceptions\ComponentsNotFoundOpenapiException;
use EugeneErg\OpenApi\Process;
use stdClass;

use function sprintf;

/**
 * Discriminator Object. Применим только вместе с oneOf/anyOf/allOf.
 *
 * Ключи $mapping — значения свойства-дискриминатора, значения — схемы,
 * которые на этапе сборки превращаются в строковые $ref.
 */
final readonly class Discriminator
{
    public AbstractSchemas $mapping;

    public function __construct(
        public string $propertyName,
        ?AbstractSchemas $mapping = null,
    ) {
        $this->mapping = $mapping ?? new Schemas();
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

        return (object) $result;
    }
}
