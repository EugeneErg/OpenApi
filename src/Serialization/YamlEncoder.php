<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Serialization;

use EugeneErg\OpenApi\Exceptions\InvalidArgumentOpenapiException;
use stdClass;

use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function sprintf;

/**
 * YAML-энкодер без внешних зависимостей.
 *
 * Покрывает ровно то подмножество, которое встречается в собранном документе:
 * карты, списки и скаляры. Якорей, тегов и множественных документов здесь нет,
 * потому что Builder их не производит.
 *
 * Скаляр печатается без кавычек только если это заведомо безопасно; во всех
 * спорных случаях используется двойная кавычка. Лишние кавычки допустимы,
 * потерянный тип — нет: ключ "200" обязан остаться строкой, а не стать числом.
 */
final readonly class YamlEncoder implements EncoderInterface
{
    private const string INDENT = '  ';

    /**
     * Слова, которые YAML 1.1 читает как булевы значения или null.
     */
    private const array RESERVED = [
        'true', 'false', 'null', 'yes', 'no', 'on', 'off', 'y', 'n', '~', '',
    ];

    public function encode(stdClass $document): string
    {
        return $this->mapBody($document, 0);
    }

    private function mapBody(stdClass $map, int $level): string
    {
        $result = '';

        foreach (Structure::vars($map) as $key => $value) {
            $result .= str_repeat(self::INDENT, $level) . $this->scalar((string) $key) . ':' . $this->value($value, $level);
        }

        return $result;
    }

    /**
     * @param array<int|string, mixed> $list
     */
    private function listBody(array $list, int $level): string
    {
        $result = '';
        $indent = str_repeat(self::INDENT, $level);

        foreach ($list as $value) {
            if ($value instanceof stdClass || is_array($value)) {
                $block = $this->block($value, $level + 1);

                // первый ключ элемента ставится на одну строку с дефисом
                $result .= $indent . '- ' . ltrim($block);

                continue;
            }

            $result .= $indent . '-' . $this->value($value, $level);
        }

        return $result;
    }

    /**
     * @param array<int|string, mixed>|stdClass $value
     */
    private function block(array|stdClass $value, int $level): string
    {
        return $value instanceof stdClass
            ? $this->mapBody($value, $level)
            : $this->listBody($value, $level);
    }

    /**
     * Хвост строки после «ключ:» или «-».
     */
    private function value(mixed $value, int $level): string
    {
        if ($value instanceof stdClass) {
            return get_object_vars($value) === []
                ? " {}\n"
                : "\n" . $this->mapBody($value, $level + 1);
        }

        if (is_array($value)) {
            return $value === []
                ? " []\n"
                : "\n" . $this->listBody($value, $level + 1);
        }

        return ' ' . $this->scalar($value) . "\n";
    }

    private function scalar(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            // 5.0 обязано остаться float, иначе при чтении станет целым
            $result = var_export($value, true);

            return str_contains($result, '.') || str_contains($result, 'E') ? $result : $result . '.0';
        }

        if (!is_string($value)) {
            throw new InvalidArgumentOpenapiException(sprintf(
                'Document contains a value of type %s, which cannot be represented in YAML.',
                get_debug_type($value),
            ));
        }

        return $this->isPlainSafe($value) ? $value : $this->quote($value);
    }

    private function isPlainSafe(string $value): bool
    {
        if (in_array(strtolower($value), self::RESERVED, true)) {
            return false;
        }

        // всё, что читается как число, обязано остаться в кавычках: коды ответов, версии
        if (preg_match('{^[+-]?(\d+\.?\d*|\.\d+)([eE][+-]?\d+)?$}', $value) === 1) {
            return false;
        }

        // «: » начинает пару, « #» — комментарий
        if (str_contains($value, ': ') || str_contains($value, ' #') || str_ends_with($value, ':')) {
            return false;
        }

        // Первый символ не должен быть индикатором YAML. Буквы берутся по Unicode,
        // иначе любое описание не на латинице уходило бы в кавычки.
        // Обратный слэш формально допустим в plain-скаляре, но в OpenAPI это regexp
        // из pattern, и читать его без кавычек легко ошибиться — поэтому тоже экранируем.
        return preg_match('{^[\p{L}\p{N}_/$][^"\'\\\\\n\r\t]*$}u', $value) === 1
            && trim($value) === $value;
    }

    private function quote(string $value): string
    {
        $escaped = strtr($value, [
            '\\' => '\\\\',
            '"' => '\"',
            "\n" => '\n',
            "\r" => '\r',
            "\t" => '\t',
        ]);

        return '"' . $escaped . '"';
    }
}
