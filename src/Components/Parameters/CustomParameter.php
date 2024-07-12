<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Parameters;

use EugeneErg\OpenApi\Process;
use stdClass;

final class CustomParameter
{
    public function __construct(public In $in, public ContentParameter $contentParameter)
    {
    }

    public function toObject(Process $process): stdClass
    {
        return (object) array_merge((array) $this->contentParameter->toObject($process), ['in' => $this->in->value]);
    }
}
