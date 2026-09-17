<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Serialization;

use stdClass;

/**
 * Parses the text of a file into the structure Reader works with.
 *
 * The other side of EncoderInterface. A separate interface is there for the same reason:
 * so that an implementation of one's own can be put in its place — over symfony/yaml, say.
 */
interface DecoderInterface
{
    /**
     * Maps have to come back as stdClass rather than as associative arrays: in JSON an
     * empty map and an empty list are distinguishable, and the difference matters.
     */
    public function decode(string $content): stdClass;
}
