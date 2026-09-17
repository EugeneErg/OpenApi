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
 * Discriminator Object. Применим только вместе с oneOf/anyOf/allOf.
 *
 * Ключи $mapping — значения свойства-дискриминатора, значения — схемы,
 * которые на этапе сборки превращаются в строковые $ref.
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
            // расширения у Discriminator Object появились только в 3.1
            $process->assertV31('Specification extensions on a discriminator');
        }

        return (object) $this->extensions->appendTo($result);
    }
}
