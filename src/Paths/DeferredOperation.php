<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Paths;

use Closure;

/**
 * A deferred reference to an operation.
 *
 * A Link points at an operation by the object rather than by a name, so an operation that
 * refers to itself — ordinary pagination, the "next page" — could not be described at
 * all otherwise: an object cannot be passed to its own constructor. The reference is
 * deferred by a closure, as with recursive schemas:
 *
 *     $listUsers = new Operation(
 *         responses: new Responses(x200: new Responses\Response(
 *             description: 'OK',
 *             links: new Links(next: new Link(
 *                 operation: new DeferredOperation(static function () use (&$listUsers): Operation {
 *                     return $listUsers;
 *                 }),
 *             )),
 *         )),
 *         id: 'listUsers',
 *     );
 */
final readonly class DeferredOperation
{
    /** @var Closure(): Operation */
    private Closure $resolver;

    /**
     * @param Closure(): Operation $resolver
     */
    public function __construct(Closure $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * The operation the reference points at.
     */
    public function resolve(): Operation
    {
        return ($this->resolver)();
    }
}
