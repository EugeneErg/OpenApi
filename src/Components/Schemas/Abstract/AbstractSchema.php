<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Abstract;

use EugeneErg\OpenApi\Exceptions\InvalidSchemaOpenapiException;
use EugeneErg\OpenApi\Extensions;
use EugeneErg\OpenApi\ExternalDocs;
use EugeneErg\OpenApi\Process;
use stdClass;

use function sprintf;

abstract readonly class AbstractSchema
{
    public Extensions $extensions;

    public function __construct(
        public ?string $type = null,
        /**
         * Открытая аннотация: JSON Schema разрешает `format` у значения любого типа,
         * а незнакомое значение инструменты просто игнорируют. У строк и чисел есть
         * enum известных значений — он лишь подсказка, строку принимают и они.
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
        public ?string $comment = null,
        public ?AbstractSchemas $defs = null,
        public ?string $id = null,
        public ?string $anchor = null,
        public ?string $dynamicAnchor = null,
        public ?self $dynamicRef = null,
        public ?Vocabularies $vocabulary = null,
        ?Extensions $extensions = null,
    ) {
        $this->extensions = $extensions ?? new Extensions();

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

        if ($vocabulary !== null && $vocabulary->items !== [] && $id === null) {
            throw new InvalidSchemaOpenapiException('"$vocabulary" is only allowed on a schema resource declaring "$id".');
        }
    }

    public function toObject(Process $process): stdClass
    {
        $result = [];

        // 3.0 помечает схему nullable отдельным флагом,
        // 3.1 — вторым типом в массиве, флага nullable там нет вовсе
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

        if ($this->nullable && !$isV31) {
            $result['nullable'] = true;
        }

        if ($this->access !== null) {
            $result[$this->access->value] = true;
        }

        if ($this->format !== null) {
            $result['format'] = $this->format;
        }

        if ($this->deprecated) {
            $result['deprecated'] = $this->deprecated;
        }

        if ($this->externalDocs !== null) {
            $result['externalDocs'] = $this->externalDocs->toObject();
        }

        if ($this->xml !== null) {
            $result['xml'] = $this->xml->toObject();
        }

        if ($this->default !== null) {
            $result['default'] = $this->default->toNative($process);
        }

        if ($this->examples !== null && $this->examples->items !== []) {
            $process->assertV31('"examples"');
            $result['examples'] = $this->examples->toNative($process);
        }

        if ($this->comment !== null) {
            $process->assertV31('"$comment"');
            $result['$comment'] = $this->comment;
        }

        if ($this->defs !== null && $this->defs->items !== []) {
            $process->assertV31('"$defs"');
            $result['$defs'] = $this->defs->sourceToObject($process);
        }

        foreach (['$id' => $this->id, '$anchor' => $this->anchor, '$dynamicAnchor' => $this->dynamicAnchor] as $keyword => $declared) {
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

            if ($this->dynamicRef->dynamicAnchor === null) {
                throw new InvalidSchemaOpenapiException(
                    '"$dynamicRef" must point at a schema that declares "$dynamicAnchor".',
                );
            }

            $result['$dynamicRef'] = '#' . $this->dynamicRef->dynamicAnchor;
        }

        return (object) $this->extensions->appendTo($result);
    }

    /**
     * Вложенная схема: если она зарегистрирована в components, на её месте будет $ref.
     */
    final protected static function nested(self $schema, Process $process): stdClass
    {
        return $process->findSchema($schema) ?? $schema->toObject($process);
    }
}
