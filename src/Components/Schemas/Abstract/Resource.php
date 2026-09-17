<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Abstract;

use EugeneErg\OpenApi\Exceptions\InvalidSchemaOpenapiException;
use EugeneErg\OpenApi\Process;

use function sprintf;

/**
 * A schema as a JSON Schema resource: its own identifier, dialect, vocabulary, anchors
 * and local definitions.
 *
 * This is not "the rarely used parameters, moved out to make the list shorter" but
 * exactly the 2020-12 core vocabulary: the specification declares these words together
 * because they speak not about the value but about the schema itself — how to address it
 * and by which rules to read it. Hence the group's shared rule as well: 3.0 has none of it.
 *
 * `$ref` does not belong here: in this package a reference is the schema object itself,
 * not a string.
 */
final readonly class Resource
{
    public function __construct(
        /**
         * The identifier of the resource. Without it the schema is a part of somebody
         * else's resource, so neither a dialect nor a vocabulary can be declared: those
         * belong to the root.
         */
        public ?string $id = null,
        public ?string $schema = null,
        public ?Vocabularies $vocabulary = null,
        public ?string $anchor = null,
        public ?string $dynamicAnchor = null,
        /** The target is a schema of its own: it has to declare a `$dynamicAnchor`. */
        public ?AbstractSchema $dynamicRef = null,
        public ?AbstractSchemas $defs = null,
        public ?string $comment = null,
    ) {
        foreach (['$anchor' => $anchor, '$dynamicAnchor' => $dynamicAnchor] as $keyword => $name) {
            if ($name !== null && preg_match('{^[A-Za-z_][A-Za-z0-9._-]*$}', $name) !== 1) {
                throw new InvalidSchemaOpenapiException(sprintf(
                    '%s must be a plain name matching [A-Za-z_][A-Za-z0-9._-]*, got "%s".',
                    $keyword,
                    $name,
                ));
            }
        }

        $defs?->assertNamed('$defs');

        $declares = [
            '$schema' => $schema !== null,
            '$vocabulary' => $vocabulary !== null && $vocabulary->items !== [],
        ];

        foreach ($declares as $keyword => $declared) {
            if ($declared && $id === null) {
                throw new InvalidSchemaOpenapiException(sprintf(
                    '"%s" is only allowed on a schema resource declaring "$id".',
                    $keyword,
                ));
            }
        }
    }

    public function isEmpty(): bool
    {
        return $this->id === null
            && $this->schema === null
            && ($this->vocabulary === null || $this->vocabulary->items === [])
            && $this->anchor === null
            && $this->dynamicAnchor === null
            && $this->dynamicRef === null
            && ($this->defs === null || $this->defs->items === [])
            && $this->comment === null;
    }

    /**
     * @param array<string, mixed> $result
     *
     * @return array<string, mixed>
     */
    public function appendTo(array $result, Process $process): array
    {
        if ($this->comment !== null) {
            $process->assertV31('"$comment"');
            $result['$comment'] = $this->comment;
        }

        if ($this->defs !== null && $this->defs->items !== []) {
            $process->assertV31('"$defs"');
            $result['$defs'] = $this->defs->sourceToObject($process);
        }

        foreach ([
            '$schema' => $this->schema,
            '$id' => $this->id,
            '$anchor' => $this->anchor,
            '$dynamicAnchor' => $this->dynamicAnchor,
        ] as $keyword => $declared) {
            if ($declared !== null) {
                $process->assertV31(sprintf('"%s"', $keyword));
                $result[$keyword] = $declared;
            }
        }

        if ($this->vocabulary !== null && $this->vocabulary->items !== []) {
            $process->assertV31('"$vocabulary"');
            $result['$vocabulary'] = $this->vocabulary->toObject();
        }

        if ($this->dynamicRef !== null) {
            $process->assertV31('"$dynamicRef"');

            // the builder writes the "#anchor" string itself: the target carries the anchor,
            // and the reference has no name of its own
            $anchor = $this->dynamicRef->resource === null ? null : $this->dynamicRef->resource->dynamicAnchor;

            if ($anchor === null) {
                throw new InvalidSchemaOpenapiException(
                    '"$dynamicRef" must point at a schema that declares "$dynamicAnchor".',
                );
            }

            $result['$dynamicRef'] = '#' . $anchor;
        }

        return $result;
    }
}
