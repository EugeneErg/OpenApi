<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Exceptions;

use Throwable;

interface OpenapiExceptionInterface extends Throwable
{
    /**
     * The place of the failure in the document — a pointer such as
     * `openapi.json/components/schemas/User/properties/tags`.
     *
     * null means the place is unknown: that happens when an object refused inside its
     * constructor, before reaching any document — there the call stack shows the place,
     * because it is a line of code rather than a line of the specification.
     */
    public function place(): ?string;

    /**
     * The same failure, one step closer to the document's root: the steps are added by
     * the containers on the way up (see Place).
     */
    public function at(string ...$segments): static;
}
