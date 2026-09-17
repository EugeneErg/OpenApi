<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Exceptions;

use Closure;

/**
 * The place of a failure in the document.
 *
 * What refuses is always the deepest object: it knows what it is missing but not where it
 * lies — and the call stack shows the insides of the package rather than the document. So
 * the place is gathered on the way up: every container adds one step of its own, and the
 * failure reaches the calling code carrying a pointer such as
 * `openapi.json/components/schemas/User/properties/tags`.
 */
final readonly class Place
{
    /**
     * @template T
     *
     * @param Closure(): T $build
     *
     * @return T
     */
    public static function in(Closure $build, int|string ...$names): mixed
    {
        try {
            return $build();
        } catch (OpenapiExceptionInterface $exception) {
            throw $exception->at(...array_map(strval(...), $names));
        }
    }
}
