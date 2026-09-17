<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi;

use EugeneErg\OpenApi\Components\Examples\Example;
use EugeneErg\OpenApi\Components\Links\Link;
use EugeneErg\OpenApi\Components\Parameters\Abstract\AbstractSchemaParameter;
use EugeneErg\OpenApi\Components\Parameters\ContentParameter;
use EugeneErg\OpenApi\Components\RequestBodies\RequestBody;
use EugeneErg\OpenApi\Components\Responses\Response;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchema;
use EugeneErg\OpenApi\Exceptions\ComponentsNotFoundOpenapiException;
use EugeneErg\OpenApi\Paths\Path;
use EugeneErg\OpenApi\Serialization\Structure;
use stdClass;

use function sprintf;

/**
 * A Reference Object with a summary and a description of its own (OpenAPI 3.1).
 *
 * Needed only where a reference has to override the component's description. In the
 * ordinary case there is still no reference to make: passing the object itself is enough,
 * and the builder writes the $ref. Here the same object is passed — addressing stays the
 * one thing it was, and by identity.
 */
final readonly class Reference
{
    public function __construct(
        public AbstractSchema|AbstractSchemaParameter|ContentParameter|Example|Link|Path|PathItems|RequestBody|Response $target,
        public ?string $summary = null,
        public ?string $description = null,
    ) {
    }

    public function toObject(Process $process): stdClass
    {
        $result = $process->refTo($this->target);

        if ($result === null) {
            throw new ComponentsNotFoundOpenapiException(sprintf(
                'Reference target of type %s is not registered in components.',
                $this->target::class,
            ));
        }

        if ($this->summary === null && $this->description === null) {
            return $result;
        }

        $process->assertV31('summary and description on a Reference Object');

        $decorated = Structure::vars($result);

        if ($this->summary !== null) {
            $decorated['summary'] = $this->summary;
        }

        if ($this->description !== null) {
            $decorated['description'] = $this->description;
        }

        return (object) $decorated;
    }
}
