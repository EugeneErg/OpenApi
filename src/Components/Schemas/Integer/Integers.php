<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Integer;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValues;
use IntBackedEnum;

/**
 * @property int[] $items
 */
final readonly class Integers extends AbstractValues
{
    public function __construct(int ...$items)
    {
        parent::__construct(...$items);
    }

    /**
     * @param class-string<IntBackedEnum> $class
     *
     * @phpstan-ignore-next-line
     */
    public static function fromEnum(string $class): self
    {
        /**
         * @phpstan-ignore-next-line
         */
        return new self(...array_column($class::cases(), 'value'));
    }
}
