<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Abstract;

use EugeneErg\OpenApi\Extensions;
use EugeneErg\OpenApi\ExternalDocs;
use EugeneErg\OpenApi\Process;
use stdClass;

abstract readonly class AbstractSchema
{
    public Extensions $extensions;

    public function __construct(
        public ?string $type = null,
        /**
         * An open annotation: JSON Schema allows `format` on a value of any type, and
         * tools simply ignore a value they do not know. Strings and numbers have an enum
         * of the known values — a hint only, they accept a plain string as well.
         */
        public ?string $format = null,
        public ?string $title = null,
        public ?string $description = null,
        public bool $nullable = false,
        public ?Access $access = null,
        public bool $deprecated = false,
        public ?ExternalDocs $externalDocs = null,
        public ?Xml $xml = null,
        public ?AbstractValue $default = null,
        public ?AbstractValues $examples = null,
        /**
         * The schema as a JSON Schema resource: `$id`, `$schema`, `$vocabulary`, the
         * anchors, `$defs`, `$comment`. This is the 2020-12 core vocabulary — words about
         * the schema itself rather than about the value — and 3.0 has none of it.
         */
        public ?Resource $resource = null,
        ?Extensions $extensions = null,
    ) {
        $this->extensions = $extensions ?? new Extensions();
    }

    public function toObject(Process $process): stdClass
    {
        $result = [];

        // 3.0 marks a schema nullable with a flag of its own; 3.1 does it with a second
        // type in the array and has no nullable flag at all
        $isV31 = $process->version()->isV31();

        if ($this->type !== null) {
            $result['type'] = $isV31 && $this->nullable ? [$this->type, 'null'] : $this->type;
        }

        if ($this->title !== null) {
            $result['title'] = $this->title;
        }

        if ($this->description !== null) {
            $result['description'] = $this->description;
        }

        // in 3.1 nullable lives as a second type, and there is nothing to spell out
        if (!$isV31 && ($this->nullable || $process->verbose)) {
            $result['nullable'] = $this->nullable;
        }

        if ($this->access !== null) {
            $result[$this->access->value] = true;
        } elseif ($process->verbose) {
            $result['readOnly'] = false;
            $result['writeOnly'] = false;
        }

        if ($this->format !== null) {
            $result['format'] = $this->format;
        }

        if ($this->deprecated || $process->verbose) {
            $result['deprecated'] = $this->deprecated;
        }

        if ($this->externalDocs !== null) {
            $result['externalDocs'] = $this->externalDocs->toObject();
        }

        if ($this->xml !== null) {
            $result['xml'] = $this->xml->toObject($process);
        }

        if ($this->default !== null) {
            $result['default'] = $this->default->toNative($process);
        }

        if ($this->examples !== null && $this->examples->items !== []) {
            $process->assertV31('"examples"');
            $result['examples'] = $this->examples->toNative($process);
        }

        $result = $this->resource?->appendTo($result, $process) ?? $result;

        return (object) $this->extensions->appendTo($result);
    }

    /**
     * A nested schema: when it is registered in components, a $ref takes its place.
     */
    final protected static function nested(self $schema, Process $process): stdClass
    {
        return $process->findSchema($schema) ?? $schema->toObject($process);
    }
}
