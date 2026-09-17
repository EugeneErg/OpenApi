<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Support;

use EugeneErg\OpenApi\Exceptions\InvalidArgumentOpenapiException;

use function is_int;
use function sprintf;
use function strlen;

/**
 * Имена элементов контейнера-карты.
 *
 * Контейнеры принимают имена именованными аргументами: `new Properties(id: $id)`
 * или `new Properties(...['created-at' => $at])`. У такой записи одна ловушка PHP:
 * ключ массива '7' или '-1' хранится как целое число, и при распаковке он становится
 * позиционным аргументом — имя теряется, а вместе с именованными PHP падает вовсе.
 *
 * Поэтому позиционный элемент в карте — всегда ошибка, а для произвольных имён
 * есть fromArray(). Он проходит через тот же конструктор, так что все проверки
 * типов и инвариантов действуют и здесь: имена лишь помечаются, чтобы PHP сохранил
 * их строками, а конструктор снимает метку.
 *
 * @internal метка — деталь реализации; публичен только fromArray()
 */
trait NamedItems
{
    private const string NAME_MARK = "\0eugene-erg/open-api:name:";

    /**
     * Контейнер с любыми именами, включая '7' и '-1'.
     *
     * @param array<array-key, mixed> $items
     */
    public static function fromArray(array $items): static
    {
        /** @phpstan-ignore argument.type, new.static */
        return new static(...self::marked($items));
    }

    /**
     * Помечает имена, чтобы PHP сохранил их строками при распаковке.
     *
     * @param array<array-key, mixed> $items
     *
     * @return array<string, mixed>
     */
    private static function marked(array $items): array
    {
        $marked = [];

        foreach ($items as $name => $item) {
            $marked[self::NAME_MARK . $name] = $item;
        }

        return $marked;
    }

    /**
     * Снимает метки и отклоняет элементы без имени.
     *
     * Ключ результата может снова оказаться int ('7' => 7): так PHP хранит такие
     * имена в любом массиве, поэтому потребители приводят ключ к строке.
     *
     * @template TItem
     *
     * @param array<array-key, TItem> $items
     *
     * @return array<array-key, TItem>
     */
    private static function named(array $items): array
    {
        $result = [];

        foreach ($items as $name => $item) {
            if (is_int($name)) {
                throw self::positional();
            }

            if (str_starts_with($name, self::NAME_MARK)) {
                $name = substr($name, strlen(self::NAME_MARK));
            }

            $result[$name] = $item;
        }

        return $result;
    }

    private static function positional(): InvalidArgumentOpenapiException
    {
        $class = substr((string) strrchr('\\' . static::class, '\\'), 1);

        return new InvalidArgumentOpenapiException(sprintf(
            '%1$s needs a name for every item. Integer-like names such as "7" or "-1" turn into '
            . 'positions when PHP spreads an array: pass such names through %1$s::fromArray().',
            $class,
        ));
    }
}
