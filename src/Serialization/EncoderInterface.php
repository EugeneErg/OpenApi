<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Serialization;

use stdClass;

/**
 * Turns a built document into the text of a file.
 *
 * A separate interface is there so that an implementation of one's own can be put in its
 * place — over symfony/yaml, say, when the project already has it.
 */
interface EncoderInterface
{
    public function encode(stdClass $document): string;
}
