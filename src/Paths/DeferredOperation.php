<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Paths;

use Closure;

/**
 * Отложенная ссылка на операцию.
 *
 * Link указывает на операцию объектом, а не именем, поэтому операция, которая
 * ссылается на саму себя — обычная пагинация, «следующая страница», — иначе
 * не описывалась бы: объект нельзя передать в собственный конструктор.
 * Ссылка откладывается замыканием, как и у рекурсивных схем:
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
     * Операция, на которую указывает ссылка.
     */
    public function resolve(): Operation
    {
        return ($this->resolver)();
    }
}
