<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Reader;

use EugeneErg\OpenApi\Serialization\Structure;
use stdClass;

use function is_array;

/**
 * What of the document has been read.
 *
 * The specification allows an object only the fields it declares and the `x-*`
 * extensions, so the package drops everything else. Dropping it silently lies to
 * whoever reads a document in order to change it and write it back: the field
 * disappears and nobody finds out. So every place that is read is marked here, and
 * `Reader::read(strict: true)` names the rest.
 *
 * The marks are kept per object of the decoded document rather than per path: the same
 * object can sit in the document under two names (a YAML anchor), and then it is read
 * once.
 */
final class Reads
{
    /** @var array<int, array<string, true>> the keys read, per object */
    private array $keys = [];

    /** @var array<int, object> objects taken whole: an example value, an extension */
    private array $whole = [];

    /**
     * @var array<int, array{object, object}> the rewritten form => the original object
     *
     * Both objects are held alive on purpose: otherwise a freed spl_object_id would be
     * handed to another object together with somebody else's marks
     */
    private array $rewritten = [];

    public function key(object $owner, string $key): void
    {
        $this->keys[spl_object_id($owner)][$key] = true;

        [, $origin] = $this->rewritten[spl_object_id($owner)] ?? [null, null];

        if ($origin !== null) {
            $this->key($origin, $key);
        }
    }

    /**
     * A rewritten form holds the same keywords, so what is read there is read in the
     * original node as well. That way a union of types, an enum and the siblings of a
     * `$ref` stay laid out as equivalent schemas, while an unknown keyword does not hide
     * behind the rewrite.
     */
    public function rewrote(object $rewritten, object $origin): void
    {
        $this->rewritten[spl_object_id($rewritten)] = [$rewritten, $origin];
    }

    /**
     * A value read whole: there is nothing to parse in it, because it holds any JSON.
     */
    public function whole(mixed $value): void
    {
        if ($value instanceof stdClass) {
            $this->whole[spl_object_id($value)] = $value;

            return;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                $this->whole($item);
            }
        }
    }

    /**
     * The places of the document the package did not read, as JSON Pointers.
     *
     * @return list<string>
     */
    public function unread(mixed $value, string $path): array
    {
        if (is_array($value)) {
            $result = [];

            foreach (array_values($value) as $index => $item) {
                $result = [...$result, ...$this->unread($item, $path . '/' . $index)];
            }

            return $result;
        }

        if (!$value instanceof stdClass || isset($this->whole[spl_object_id($value)])) {
            return [];
        }

        $read = $this->keys[spl_object_id($value)] ?? [];
        $result = [];

        foreach (Structure::vars($value) as $key => $item) {
            $key = (string) $key;
            $child = $path . '/' . str_replace(['~', '/'], ['~0', '~1'], $key);

            if (!isset($read[$key])) {
                $result[] = $child;

                continue;
            }

            $result = [...$result, ...$this->unread($item, $child)];
        }

        return $result;
    }
}
