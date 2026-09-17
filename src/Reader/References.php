<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Reader;

use Closure;
use EugeneErg\OpenApi\Tags\Tag;

/**
 * The shared state of reference resolution.
 *
 * Kept out of Registry because Registry is cloned when the reader moves between files,
 * while the register of declared components has to stay common to all of them.
 */
final class References
{
    /** @var array<string, object> */
    public array $resolved = [];

    /** @var array<string, true> */
    public array $building = [];

    /** @var array<string, Closure(Node): object> */
    public array $factories = [];

    /** @var array<string, Node> */
    public array $nodes = [];

    /** @var array<string, string> operationId => pointer */
    public array $operationIds = [];

    /** @var array<string, Tag> the ones the top level declares, by name */
    public array $tags = [];
}
