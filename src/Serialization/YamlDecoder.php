<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Serialization;

use EugeneErg\OpenApi\Exceptions\InvalidDocumentOpenapiException;
use RuntimeException;
use stdClass;

use function function_exists;

/**
 * Разбор YAML через расширение ext-yaml.
 *
 * Собственного парсера в пакете нет намеренно: YAML 1.2 — это якоря, ссылки,
 * блочные скаляры и поточный синтаксис, и неполная реализация молча испортила бы
 * чужой документ вместо того, чтобы отказаться его читать.
 *
 * Если ext-yaml недоступен, реализуйте DecoderInterface поверх symfony/yaml —
 * это несколько строк, пример есть в README. Чтение YAML от этого не закрыто.
 */
final readonly class YamlDecoder implements DecoderInterface
{
    public static function isAvailable(): bool
    {
        return function_exists('yaml_parse');
    }

    public function decode(string $content): stdClass
    {
        if (!self::isAvailable()) {
            throw new RuntimeException(
                'Reading YAML requires the ext-yaml extension. Install it, or implement '
                . 'DecoderInterface over another parser such as symfony/yaml.',
            );
        }

        $result = @yaml_parse($content);

        if ($result === false) {
            throw new InvalidDocumentOpenapiException('Document is not valid YAML.');
        }

        return Structure::toObject($result);
    }
}
