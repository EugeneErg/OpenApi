<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Reader;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValues;
use EugeneErg\OpenApi\Components\Schemas\Array\OpenapiArray;
use EugeneErg\OpenApi\Components\Schemas\Object\OpenapiObject;
use EugeneErg\OpenApi\Exceptions\InvalidArgumentOpenapiException;
use EugeneErg\OpenApi\Exceptions\InvalidDocumentOpenapiException;
use EugeneErg\OpenApi\Extensions;
use EugeneErg\OpenApi\Serialization\Structure;
use stdClass;

use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_scalar;
use function is_string;
use function sprintf;

/**
 * Узел разобранного документа вместе со своим путём.
 *
 * Путь нужен только для сообщений об ошибках: без него «expected a string» посреди
 * чужой спецификации на две тысячи строк бесполезно.
 *
 * Здесь же снимается неоднозначность пустых коллекций: через ext-yaml `{}` и `[]`
 * приходят одинаково, а каждый accessor знает, что ожидается в этом месте.
 */
final readonly class Node
{
    /**
     * @param bool $present false — ключа в документе нет; null в документе и отсутствие различаются
     */
    public function __construct(
        public mixed $value,
        public string $path = '',
        public bool $present = true,
    ) {
    }

    public function has(string $key): bool
    {
        return $this->value instanceof stdClass && property_exists($this->value, $key);
    }

    public function get(string $key): self
    {
        if (!$this->has($key)) {
            return new self(null, $this->child($key), present: false);
        }

        /** @var stdClass $value */
        $value = $this->value;

        return new self($value->{$key}, $this->child($key));
    }

    /**
     * Ключ есть в документе, даже если его значение null.
     */
    public function isPresent(): bool
    {
        return $this->present;
    }

    public function isMissing(): bool
    {
        return $this->value === null;
    }

    public function string(): string
    {
        if (!is_string($this->value)) {
            throw $this->unexpected('a string');
        }

        return $this->value;
    }

    public function stringOrNull(): ?string
    {
        return $this->isMissing() ? null : $this->string();
    }

    public function bool(): bool
    {
        if (!is_bool($this->value)) {
            throw $this->unexpected('a boolean');
        }

        return $this->value;
    }

    public function boolOr(bool $default): bool
    {
        return $this->isMissing() ? $default : $this->bool();
    }

    public function int(): int
    {
        if (!is_int($this->value)) {
            throw $this->unexpected('an integer');
        }

        return $this->value;
    }

    public function intOrNull(): ?int
    {
        return $this->isMissing() ? null : $this->int();
    }

    /**
     * Число как оно записано: целое остаётся целым.
     *
     * Через float границы вроде `9223372036854775807` не проходят: приведение
     * туда и обратно ломает значение.
     */
    public function numberOrNull(): float|int|null
    {
        if ($this->isMissing()) {
            return null;
        }

        return is_int($this->value) || is_float($this->value) ? $this->value : throw $this->unexpected('a number');
    }

    public function floatOrNull(): ?float
    {
        if ($this->isMissing()) {
            return null;
        }

        if (!is_int($this->value) && !is_float($this->value)) {
            throw $this->unexpected('a number');
        }

        return (float) $this->value;
    }

    /**
     * Карта «ключ => узел». Пустой список здесь читается как пустая карта.
     *
     * Ключ может оказаться int: PHP приводит числовое имя свойства к целому,
     * и обратно в строку массив его не пустит. Вызывающий код приводит сам.
     *
     * @return array<int|string, self>
     */
    public function map(): array
    {
        if ($this->isMissing() || $this->value === []) {
            return [];
        }

        if (!$this->value instanceof stdClass) {
            throw $this->unexpected('a mapping');
        }

        $result = [];

        // PHP отдаёт числовое имя свойства как int: «200» превратилось бы в 200
        foreach (Structure::vars($this->value) as $key => $item) {
            $result[(string) $key] = new self($item, $this->child((string) $key));
        }

        return $result;
    }

    /**
     * @return list<self>
     */
    public function list(): array
    {
        if ($this->isMissing()) {
            return [];
        }

        // пустая карта из ext-yaml неотличима от пустого списка
        if ($this->value instanceof stdClass && Structure::vars($this->value) === []) {
            return [];
        }

        if (!is_array($this->value)) {
            throw $this->unexpected('a list');
        }

        $result = [];

        foreach (array_values($this->value) as $index => $item) {
            $result[] = new self($item, $this->child((string) $index));
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    public function strings(): array
    {
        return array_map(static fn (self $item): string => $item->string(), $this->list());
    }

    /**
     * Карта расширяемого объекта: Paths, Responses и Callback Object держат
     * элементы и расширения вперемешку, и `x-*` здесь не элементы.
     *
     * У обычной карты такого разделения нет: в components.headers имя
     * `x-common-marker-version` — это имя компонента, а не расширение.
     *
     * @return array<int|string, self>
     */
    public function extensibleMap(): array
    {
        return array_filter(
            $this->map(),
            static fn (int|string $name): bool => !str_starts_with((string) $name, 'x-'),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * Расширения `x-*` этого объекта; префикс снимается — его добавляет Extensions.
     */
    public function extensions(): ?Extensions
    {
        if (!$this->value instanceof stdClass) {
            return null;
        }

        $items = [];

        foreach (Structure::vars($this->value) as $name => $value) {
            $name = (string) $name;

            if (str_starts_with($name, 'x-')) {
                $items[substr($name, 2)] = self::native($value, $this->child($name));
            }
        }

        if ($items === []) {
            return null;
        }

        try {
            return Extensions::fromArray($items);
        } catch (InvalidArgumentOpenapiException $exception) {
            throw new InvalidDocumentOpenapiException(sprintf('%s: %s', $this->path, $exception->getMessage()));
        }
    }

    public function unexpected(string $expected): InvalidDocumentOpenapiException
    {
        return new InvalidDocumentOpenapiException(sprintf(
            '%s: expected %s, got %s.',
            $this->path === '' ? 'Document root' : $this->path,
            $expected,
            get_debug_type($this->value),
        ));
    }

    /**
     * Путь дочернего узла — настоящий JSON Pointer (RFC 6901), потому что по нему
     * реестр ищет объявленные ссылки: шаблон пути `/users` внутри указателя
     * обязан быть записан как `~1users`, иначе разделитель не отличить от имени.
     */
    private function child(string $key): string
    {
        return $this->path . '/' . str_replace(['~', '/'], ['~0', '~1'], $key);
    }

    /**
     * Значение расширения — любое значение JSON.
     */
    private static function native(mixed $value, string $path): AbstractValues|bool|float|int|string|null
    {
        if ($value instanceof stdClass) {
            $items = [];

            foreach (Structure::vars($value) as $name => $item) {
                $items[$name] = self::native($item, $path . '/' . $name);
            }

            return OpenapiObject::fromArray($items);
        }

        if (is_array($value)) {
            $items = [];

            foreach (array_values($value) as $index => $item) {
                $items[] = self::native($item, $path . '/' . $index);
            }

            return new OpenapiArray(...$items);
        }

        return is_scalar($value) || $value === null
            ? $value
            : throw new InvalidDocumentOpenapiException(sprintf('%s: expected a JSON value.', $path));
    }
}
