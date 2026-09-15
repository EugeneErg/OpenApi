<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Serialization;

use EugeneErg\OpenApi\Exceptions\InvalidDocumentOpenapiException;
use stdClass;

use function is_array;
use function is_object;
use function sprintf;

/**
 * Приведение результата YAML-парсера к той же форме, что даёт json_decode:
 * карты — stdClass, списки — массивы.
 */
final readonly class Structure
{
    public static function toObject(mixed $value): stdClass
    {
        $result = self::normalize($value);

        if (!$result instanceof stdClass) {
            throw new InvalidDocumentOpenapiException(
                sprintf('Document must be a mapping at the top level, got %s.', get_debug_type($result)),
            );
        }

        return $result;
    }

    public static function normalize(mixed $value): mixed
    {
        if (is_object($value)) {
            $value = get_object_vars($value);
        } elseif (!is_array($value)) {
            return $value;
        }

        // Пустая структура неотличима: и `{}`, и `[]` приходят пустым массивом.
        // Оставляем списком, а Reader принимает обе формы там, где ждёт карту.
        if ($value === [] || array_is_list($value)) {
            return array_map(self::normalize(...), $value);
        }

        $result = [];

        foreach ($value as $key => $item) {
            $result[(string) $key] = self::normalize($item);
        }

        return (object) $result;
    }
}
