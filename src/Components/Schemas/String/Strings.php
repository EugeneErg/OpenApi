<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\String;

use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValues;
use StringBackedEnum;

/**
 * @property string[] $items
 */
final readonly class Strings extends AbstractValues
{
    public function __construct(string ...$items)
    {
        parent::__construct(...$items);
    }

    /**
     * @param class-string<StringBackedEnum> $class
     *
     * @phpstan-ignore-next-line
     */
    public static function fromEnum(string $class): self
    {
        /** @phpstan-ignore-next-line */
        return new self(...array_column($class::cases(), 'value'));
    }
}
