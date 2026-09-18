<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Links\Link;

use JsonException;
use JsonSerializable;
use stdClass;

final readonly class Parameter
{
    private function __construct(public string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * An arbitrary runtime expression.
     *
     * The named constructors cover the usual forms, but the specification allows any
     * expression at all, and reading a finished document brings it in as a string.
     */
    public static function expression(string $value): self
    {
        return new self($value);
    }

    public static function requestPath(string $value): self
    {
        return new self('$request.path.' . $value);
    }

    public static function requestQuery(string $value): self
    {
        return new self('$request.query.' . $value);
    }

    public static function requestHeader(string $value): self
    {
        return new self('$request.header.' . $value);
    }

    public static function requestBody(?string $value = null): self
    {
        return new self('$request.body' . self::pointer($value));
    }

    public static function responseHeader(string $value): self
    {
        return new self('$response.header.' . $value);
    }

    public static function responseBody(?string $value = null): self
    {
        return new self('$response.body' . self::pointer($value));
    }

    public static function constant(string $value): self
    {
        return new self($value);
    }

    /**
     * A constant that is not a string: the value is written as JSON.
     *
     * @param null|array<array-key, mixed>|bool|float|int|JsonSerializable|stdClass|string $value
     *
     * @throws JsonException
     */
    public static function json(array|bool|float|int|JsonSerializable|stdClass|string|null $value): self
    {
        return new self(json_encode($value, JSON_THROW_ON_ERROR));
    }

    /**
     * The fragment of a body expression: a JSON Pointer, written as one.
     *
     * A pointer starts with a slash (RFC 6901), and both spellings mean the same thing
     * here — `responseBody('/id')` and `responseBody('id')` give `#/id`. Before, the
     * slash was always added, so the natural `'/id'` quietly became `#//id`: a pointer to
     * the member "" of the member "id". `expression()` is there for anything else,
     * including that.
     */
    private static function pointer(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return '#' . (str_starts_with($value, '/') ? $value : '/' . $value);
    }
}
