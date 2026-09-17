<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Abstract;

use Closure;
use EugeneErg\OpenApi\Exceptions\InvalidSchemaOpenapiException;
use EugeneErg\OpenApi\Process;
use stdClass;

/**
 * A deferred reference to a schema.
 *
 * Needed for recursion: an object cannot be passed to its own constructor, so a schema
 * that refers to itself is declared through a closure over a reference:
 *
 *     $node = new Schemas\Object\Schema(
 *         properties: new Schemas\Object\Properties(
 *             children: new Schemas\Object\Property(
 *                 schema: new Schemas\Array\Schema(
 *                     items: new DeferredSchema(static function () use (&$node) { return $node; }),
 *                 ),
 *             ),
 *         ),
 *     );
 *
 * The target has to live in components.schemas or in $defs: a recursive schema must be
 * addressable, or unfolding it never ends. That is the specification's requirement, not
 * the package's.
 *
 * The binding goes through use (&$var), so such a declaration is best kept in a scope of
 * its own: a file pulled in by require inherits the caller's variables.
 */
final readonly class DeferredSchema extends AbstractSchema
{
    /** @var Closure(): AbstractSchema */
    private Closure $resolver;

    /**
     * @param Closure(): AbstractSchema $resolver
     */
    public function __construct(Closure $resolver)
    {
        $this->resolver = $resolver;

        parent::__construct();
    }

    /**
     * The schema the reference points at. A chain of deferred references unfolds whole.
     */
    public function resolve(): AbstractSchema
    {
        $target = ($this->resolver)();
        $seen = [spl_object_id($this) => true];

        while ($target instanceof self) {
            if (isset($seen[spl_object_id($target)])) {
                throw new InvalidSchemaOpenapiException('A deferred schema resolves to itself.');
            }

            $seen[spl_object_id($target)] = true;
            $target = $target->resolve();
        }

        return $target;
    }

    public function toObject(Process $process): stdClass
    {
        $target = $this->resolve();
        $result = $process->findSchema($target);

        if ($result === null) {
            throw new InvalidSchemaOpenapiException(
                'A deferred schema must point at a schema registered in components.schemas or in $defs, '
                . 'otherwise the reference cannot be expressed as $ref.',
            );
        }

        return $result;
    }
}
