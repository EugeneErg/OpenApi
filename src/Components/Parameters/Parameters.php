<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Parameters;

use EugeneErg\OpenApi\Components\Parameters\Abstract\AbstractParameter;
use EugeneErg\OpenApi\Components\Parameters\Abstract\AbstractParameters;
use EugeneErg\OpenApi\Components\Parameters\Abstract\AbstractSchemaParameter;
use EugeneErg\OpenApi\Components\Parameters\Cookie\Cookies;
use EugeneErg\OpenApi\Components\Parameters\Header\Headers;
use EugeneErg\OpenApi\Components\Parameters\Path\Paths;
use EugeneErg\OpenApi\Components\Parameters\Query\Queries;
use EugeneErg\OpenApi\Exceptions\ComponentsNotFoundOpenapiException;
use EugeneErg\OpenApi\Exceptions\InvalidArgumentOpenapiException;
use EugeneErg\OpenApi\Process;
use EugeneErg\OpenApi\Reference;
use EugeneErg\OpenApi\Serialization\Structure;
use stdClass;

use function sprintf;

final readonly class Parameters
{
    /** @var list<AbstractParameters> */
    public array $items;
    public Headers $headers;
    public Cookies $cookies;
    public Paths $paths;
    public Queries $queries;

    public function __construct(
        ?Headers $headers = null,
        ?Cookies $cookies = null,
        ?Paths $paths = null,
        ?Queries $queries = null,
    ) {
        $this->headers = $headers ?? new Headers();
        $this->cookies = $cookies ?? new Cookies();
        $this->paths = $paths ?? new Paths();
        $this->queries = $queries ?? new Queries();
        $this->items = array_values(array_filter(
            [$headers, $cookies, $paths, $queries],
            static fn (?AbstractParameters $item): bool => $item !== null,
        ));
    }

    /**
     * @return array<int, stdClass>
     */
    public function toArray(Process $process): array
    {
        $result = [];

        // «A unique parameter is defined by a combination of a name and location»:
        // повтор в одном списке — испорченный документ, и ссылкой он не спрячется
        $seen = [];

        foreach ($this->items as $parameter) {
            $in = $parameter->in();

            foreach ($parameter->items as $name => $item) {
                if ($item instanceof Reference) {
                    // у ссылки имя берётся из объявления компонента
                    $result[] = $item->toObject($process);
                    self::assertUnique($seen, $in, $this->declaredName($process, $in, $item) ?? (string) $name);

                    continue;
                }

                self::assertUnique($seen, $in, (string) $name);
                $searchItem = $item instanceof AbstractSchemaParameter ? $item : new CustomParameter($in, $item);
                $result[] = $process->findParameter($searchItem)
                    ?? (object) array_merge(
                        Structure::vars($item->toObject($process)),
                        ['name' => (string) $name, 'in' => $in->value],
                    );
            }

            foreach ($parameter->registered as $item) {
                $name = $this->declaredName($process, $in, $item);

                if ($item instanceof Reference) {
                    $result[] = $item->toObject($process);
                    self::assertUnique($seen, $in, $name ?? '');

                    continue;
                }

                $searchItem = $item instanceof AbstractSchemaParameter ? $item : new CustomParameter($in, $item);
                $result[] = $process->findParameter($searchItem) ?? throw new ComponentsNotFoundOpenapiException(sprintf(
                    'A %s parameter was passed without a name, but it is not registered in components.parameters: '
                    . 'pass it by name, or register it so the name comes from the registration.',
                    $in->value,
                ));
                self::assertUnique($seen, $in, $name ?? '');
            }
        }

        return $result;
    }

    /**
     * Имя параметра, объявленное в components: для ссылки и для параметра,
     * переданного без имени, другого источника имени нет.
     */
    private function declaredName(Process $process, In $in, AbstractParameter|Reference $item): ?string
    {
        $target = $item instanceof Reference ? $item->target : $item;

        if (!$target instanceof AbstractSchemaParameter && !$target instanceof ContentParameter) {
            return null;
        }

        return $process->openapi->findParameterName(
            $target instanceof AbstractSchemaParameter ? $target : new CustomParameter($in, $target),
        );
    }

    /**
     * @param array<string, true> $seen
     */
    private static function assertUnique(array &$seen, In $in, string $name): void
    {
        $key = $in->value . ' ' . $name;

        if (isset($seen[$key])) {
            throw new InvalidArgumentOpenapiException(sprintf(
                'Parameter "%s" in %s is listed twice: a parameter is unique by its name and location.',
                $name,
                $in->value,
            ));
        }

        $seen[$key] = true;
    }
}
