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
 * A YAML encoder with no external dependencies.
 *
 * It covers exactly the subset a built document contains: maps, lists and scalars. There
 * are no anchors, tags or multiple documents here, because Builder produces none.
 *
 * A scalar is printed without quotes only when that is certainly safe; in every doubtful
 * case a double quote is used. Superfluous quotes are acceptable, a lost type is not: the
 * key "200" has to stay a string rather than become a number.
 */
final readonly class YamlEncoder implements EncoderInterface
{
    private const string INDENT = '  ';

    /**
     * The words YAML 1.1 reads as booleans or as null.
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

                // an empty map and an empty list have to be written out, or the dash is
                // left without a value and the next line sticks to it
                if ($block === '') {
                    $result .= $indent . '-' . $this->value($value, $level);

                    continue;
                }

                // the item's first key goes on the same line as the dash
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
     * The tail of a line after "key:" or "-".
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
            // 5.0 has to stay a float, or reading it back gives an integer
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

        // whatever reads as a number has to stay quoted: response codes, versions
        if (preg_match('{^[+-]?(\d+\.?\d*|\.\d+)([eE][+-]?\d+)?$}', $value) === 1) {
            return false;
        }

        // ": " starts a pair, " #" starts a comment
        if (str_contains($value, ': ') || str_contains($value, ' #') || str_ends_with($value, ':')) {
            return false;
        }

        // The first character must not be a YAML indicator. Letters are taken by
        // Unicode, or any description not in Latin would go into quotes.
        // A backslash is formally admissible in a plain scalar, but in OpenAPI it is a
        // regexp out of pattern, and reading that unquoted invites a mistake — so it is
        // quoted as well.
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
