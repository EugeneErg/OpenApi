<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\RequestBodies;

use EugeneErg\OpenApi\Exceptions\InvalidArgumentOpenapiException;
use EugeneErg\OpenApi\Process;
use EugeneErg\OpenApi\Support\NamedItems;
use stdClass;

use function sprintf;

final readonly class Contents
{
    use NamedItems;

    /** @var array<array-key, Content> */
    public array $items;

    public function __construct(Content ...$contents)
    {
        $this->items = self::named($contents);

        foreach ($this->items as $mediaType => $content) {
            if ($content->encoding->items !== [] && !self::acceptsEncoding((string) $mediaType)) {
                throw new InvalidArgumentOpenapiException(sprintf(
                    'Encoding applies only to multipart and application/x-www-form-urlencoded content, not to "%s".',
                    $mediaType,
                ));
            }
        }
    }

    /**
     * Encoding Object действует только для этих типов; у остальных он ничего не меняет.
     */
    public static function acceptsEncoding(string $mediaType): bool
    {
        $type = strtolower(trim(explode(';', $mediaType, 2)[0]));

        return $type === 'application/x-www-form-urlencoded' || str_starts_with($type, 'multipart/');
    }

    public function toObject(Process $process): stdClass
    {
        $result = [];

        foreach ($this->items as $name => $item) {
            $result[$name] = $item->toObject($process);
        }

        return (object) $result;
    }
}
