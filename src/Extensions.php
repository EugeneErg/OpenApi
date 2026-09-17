<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValues;
use EugeneErg\OpenApi\Components\Schemas\Object\OpenapiObject;
use EugeneErg\OpenApi\Exceptions\InvalidArgumentOpenapiException;
use EugeneErg\OpenApi\Serialization\Structure;
use EugeneErg\OpenApi\Support\NamedItems;
use stdClass;

/**
 * Расширения спецификации — поля `x-*`.
 *
 * Спецификация требует от имени ровно одного: оно начинается с `x-`. Этот префикс
 * добавляется всегда, поэтому имя пишется без него:
 *
 *     new Extensions(internal: true)                   // x-internal
 *     new Extensions(...['code-samples' => $samples])  // x-code-samples
 *     new Extensions(...['x-legacy' => $value])        // x-x-legacy
 *
 * Правило одно и без исключений, так что у каждого поля документа ровно одна запись,
 * а `x-x-legacy`, которое спецификация разрешает, выражается как `x-legacy`.
 * Обратная сторона: префикс никогда не пишут руками — написанный, он удвоится.
 *
 * Имена вида `7` передаются через fromArray(), как и в остальных картах.
 * Значение — любое значение JSON: скаляр, null либо контейнер
 * (`OpenapiObject`, `OpenapiArray`).
 *
 * Префиксы `x-oai-` и `x-oas-` спецификация 3.1 оставила за OpenAPI Initiative:
 * пакет их принимает — документ с ними законен, — но своё расширение так называть
 * не стоит.
 */
final readonly class Extensions
{
    use NamedItems;

    /** @var array<array-key, null|AbstractValues|bool|float|int|string> имена без префикса */
    public array $items;

    public function __construct(AbstractValues|bool|float|int|string|null ...$extensions)
    {
        $this->items = self::named($extensions);

        foreach (array_keys($this->items) as $name) {
            if ((string) $name === '') {
                throw new InvalidArgumentOpenapiException('Extension name must not be empty.');
            }
        }
    }

    /**
     * Дописывает расширения в конец полей объекта.
     *
     * Подкласс дополняет вывод родителя, поэтому расширения переносятся в конец
     * при каждом вызове: иначе они оказались бы в середине объекта.
     *
     * @param array<array-key, mixed>|stdClass $fields
     *
     * @return array<array-key, mixed>
     */
    public function appendTo(array|stdClass $fields): array
    {
        $fields = $fields instanceof stdClass ? Structure::vars($fields) : $fields;

        foreach ($this->items as $name => $value) {
            unset($fields['x-' . $name]);

            $fields['x-' . $name] = self::native($value);
        }

        return $fields;
    }

    /**
     * @return null|array<array-key, mixed>|bool|float|int|stdClass|string
     */
    private static function native(
        AbstractValues|bool|float|int|string|null $value,
    ): array|bool|float|int|stdClass|string|null {
        if (!$value instanceof AbstractValues) {
            return $value;
        }

        $result = [];

        foreach ($value->items as $name => $item) {
            $result[$name] = $item instanceof AbstractValues ? self::native($item) : $item;
        }

        return $value instanceof OpenapiObject ? (object) $result : array_values($result);
    }
}
