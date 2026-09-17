<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Object;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchema;
use EugeneErg\OpenApi\Process;
use EugeneErg\OpenApi\Support\NamedItems;
use stdClass;

/**
 * patternProperties: the key is a regular expression, the value is a schema.
 *
 * A regular expression cannot be passed as a named argument, so unpacking is used instead:
 * `new PatternProperties(...['^x-' => $schema])`.
 */
final readonly class PatternProperties
{
    use NamedItems;

    /** @var array<array-key, AbstractSchema> */
    public array $items;

    public function __construct(AbstractSchema ...$schemas)
    {
        $this->items = self::named($schemas);
    }

    public function toObject(Process $process): stdClass
    {
        $result = [];

        foreach ($this->items as $pattern => $schema) {
            $result[$pattern] = $process->findSchema($schema) ?? $schema->toObject($process);
        }

        return (object) $result;
    }
}
