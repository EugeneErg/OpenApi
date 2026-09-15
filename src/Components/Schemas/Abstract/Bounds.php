<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Abstract;

use EugeneErg\OpenApi\Exceptions\InvalidSchemaOpenapiException;

use function sprintf;

/**
 * Проверки границ, которые нельзя выразить типом.
 *
 * Неотрицательность ловится статически через `int<0, max>` в сигнатурах,
 * а вот «нижняя граница не больше верхней» известна только в рантайме.
 */
trait Bounds
{
    private static function assertRange(string $what, null|float|int $min, null|float|int $max): void
    {
        if ($min !== null && $max !== null && $min > $max) {
            throw new InvalidSchemaOpenapiException(sprintf(
                '%s: lower bound (%s) cannot be greater than upper bound (%s).',
                $what,
                (string) $min,
                (string) $max,
            ));
        }
    }

    private static function assertPositive(string $what, null|float|int $value): void
    {
        if ($value !== null && $value <= 0) {
            throw new InvalidSchemaOpenapiException(sprintf('%s must be greater than zero, got %s.', $what, (string) $value));
        }
    }
}
