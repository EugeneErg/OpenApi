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
 * Reference Object с собственными summary и description (OpenAPI 3.1).
 *
 * Нужен только там, где ссылка должна переопределить описание компонента.
 * В обычном случае ссылку по-прежнему делать не надо: достаточно передать сам
 * объект, и сборщик подставит $ref. Здесь указывается тот же объект — адресация
 * остаётся единственной и по идентичности.
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
