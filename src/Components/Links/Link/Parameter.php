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
        return new self('$request.body' . ($value === null ? '' : '#/' . $value));
    }

    public static function responseHeader(string $value): self
    {
        return new self('$response.header.' . $value);
    }

    public static function responseBody(?string $value = null): self
    {
        return new self('$response.body' . ($value === null ? '' : '#/' . $value));
    }

    public static function constant(string $value): self
    {
        return new self($value);
    }

    /**
     * @param null|array{}|bool|float|int|JsonSerializable|stdClass|string $value
     *
     * @throws JsonException
     */
    public static function json(array|bool|float|int|JsonSerializable|stdClass|string|null $value): self
    {
        return new self(json_encode($value, JSON_THROW_ON_ERROR));
    }
}
