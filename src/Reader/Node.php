<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Reader;

use EugeneErg\OpenApi\Exceptions\InvalidDocumentOpenapiException;
use stdClass;

use function is_array;
use function is_bool;
use function is_float;
use function is_int;
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
    public function __construct(
        public mixed $value,
        public string $path = '',
    ) {
    }

    public function has(string $key): bool
    {
        return $this->value instanceof stdClass && property_exists($this->value, $key);
    }

    public function get(string $key): self
    {
        if (!$this->has($key)) {
            return new self(null, $this->child($key));
        }

        /** @var stdClass $value */
        $value = $this->value;

        return new self($value->{$key}, $this->child($key));
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
        foreach (get_object_vars($this->value) as $key => $item) {
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
        if ($this->value instanceof stdClass && get_object_vars($this->value) === []) {
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

    public function unexpected(string $expected): InvalidDocumentOpenapiException
    {
        return new InvalidDocumentOpenapiException(sprintf(
            '%s: expected %s, got %s.',
            $this->path === '' ? 'Document root' : $this->path,
            $expected,
            get_debug_type($this->value),
        ));
    }

    private function child(string $key): string
    {
        return $this->path . '/' . $key;
    }
}
