<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Abstract;

use Closure;
use EugeneErg\OpenApi\Exceptions\InvalidSchemaOpenapiException;
use EugeneErg\OpenApi\Process;
use stdClass;

/**
 * Отложенная ссылка на схему.
 *
 * Нужна для рекурсии: объект нельзя передать в собственный конструктор, поэтому
 * схема, ссылающаяся на саму себя, объявляется через замыкание по ссылке:
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
 * Цель обязана лежать в components.schemas или в $defs: рекурсивная схема должна
 * быть адресуемой, иначе её разворачивание не имеет конца. Это требование самой
 * спецификации, а не пакета.
 *
 * Связывание идёт через use (&$var), поэтому объявление стоит держать в собственной
 * области видимости: файл, подключённый через require, унаследует переменные вызывающего.
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
     * Схема, на которую указывает ссылка. Цепочка отложенных ссылок разворачивается целиком.
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
