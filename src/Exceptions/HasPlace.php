<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Exceptions;

use function implode;
use function sprintf;

/**
 * The place of a failure, which the containers add to on the way up to the document's root.
 *
 * The exception stays the same object through all of it: the call stack points at where
 * the failure happened, and the message at where that is in the document.
 */
trait HasPlace
{
    private ?string $place = null;

    /** The message without the place: the place is prepended, and rewritten every time. */
    private ?string $reason = null;

    /**
     * The pointer to the place of the failure; null means nobody outside has said where
     * it is yet.
     */
    public function place(): ?string
    {
        return $this->place;
    }

    public function at(string ...$segments): static
    {
        if ($segments === []) {
            return $this;
        }

        $this->reason ??= $this->message;
        $this->place = implode('/', array_map(self::escapeSegment(...), $segments))
            . ($this->place === null ? '' : '/' . $this->place);
        $this->message = sprintf('%s: %s', $this->place, $this->reason);

        return $this;
    }

    /**
     * A path template `/users` inside a pointer is written as `~1users` (RFC 6901), or
     * the separator cannot be told from the name.
     */
    private static function escapeSegment(string $segment): string
    {
        return str_replace(['~', '/'], ['~0', '~1'], $segment);
    }
}
