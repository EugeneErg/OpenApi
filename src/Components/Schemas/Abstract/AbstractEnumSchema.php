<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Abstract;

use EugeneErg\OpenApi\Exceptions\InvalidSchemaOpenapiException;
use EugeneErg\OpenApi\Extensions;
use EugeneErg\OpenApi\ExternalDocs;
use EugeneErg\OpenApi\Process;
use EugeneErg\OpenApi\Serialization\Structure;
use stdClass;

use function count;
use function sprintf;

/**
 * Схема с закрытым набором значений: `enum`, а для одного значения — `const`.
 *
 * Почему это отдельный вид схемы, а не параметр `enum` у обычной.
 * Когда значения перечислены, любое проверяющее ключевое слово (minLength,
 * pattern, minimum, items, properties, allOf, not…) либо выполняется для всех
 * значений и ничего не меняет, либо отсекает часть из них, и тогда это ошибка.
 * Третьего не бывает, поэтому таких параметров здесь нет вовсе: написать
 * бесполезное нельзя, а полезного не теряется. Остались только аннотации —
 * то, что меняет смысл документа, а не множество допустимых значений.
 * `example` тоже убран: пример для перечисления ничего не добавляет.
 *
 * Если перечисление всё же нужно сочетать с композицией, это выражается
 * через `allOf` — такая запись эквивалентна.
 *
 * Как и nullable в целом, форма вывода зависит от версии: одно значение
 * в 3.1 печатается как `const`, в 3.0 — как `enum` из одного элемента;
 * nullable добавляет `null` в сам перечень — иначе по 3.0.3 null недопустим.
 */
abstract readonly class AbstractEnumSchema extends AbstractSchema
{
    public function __construct(
        public AbstractValues $enums,
        ?string $type,
        ?string $format = null,
        ?string $title = null,
        ?string $description = null,
        bool $nullable = false,
        ?Access $access = null,
        bool $deprecated = false,
        ?ExternalDocs $externalDocs = null,
        ?Xml $xml = null,
        ?AbstractValue $default = null,
        ?string $comment = null,
        ?AbstractSchemas $defs = null,
        ?string $id = null,
        ?string $anchor = null,
        ?string $dynamicAnchor = null,
        ?Vocabularies $vocabulary = null,
        ?Extensions $extensions = null,
    ) {
        if ($enums->items === []) {
            throw new InvalidSchemaOpenapiException(
                'Enum must list at least one value; a schema that accepts nothing is written as not: {}.',
            );
        }

        $known = [];

        foreach ($enums->items as $value) {
            if ($value === null) {
                throw new InvalidSchemaOpenapiException(
                    'Do not list null among enum values: set nullable: true, the package adds null itself.',
                );
            }

            $key = JsonValue::key($value);

            if (isset($known[$key])) {
                throw new InvalidSchemaOpenapiException(sprintf('Enum lists %s more than once.', self::show($value)));
            }

            $known[$key] = true;
        }

        if ($default !== null && $default->value === null && !$nullable) {
            throw new InvalidSchemaOpenapiException('Default null requires nullable: true.');
        }

        if ($default !== null && $default->value !== null) {
            if (!isset($known[JsonValue::key($default->value)])) {
                throw new InvalidSchemaOpenapiException(sprintf(
                    'Default %s is not one of the enum values.',
                    self::show($default->value),
                ));
            }
        }

        parent::__construct(
            type: $type,
            format: $format,
            title: $title,
            description: $description,
            nullable: $nullable,
            access: $access,
            deprecated: $deprecated,
            externalDocs: $externalDocs,
            xml: $xml,
            default: $default,
            comment: $comment,
            defs: $defs,
            id: $id,
            anchor: $anchor,
            dynamicAnchor: $dynamicAnchor,
            vocabulary: $vocabulary,
            extensions: $extensions,
        );

        // после родителя: проверка смотрит на format, а он объявлен там
        foreach ($enums->items as $value) {
            $this->assertValue($value);
        }
    }

    public function toObject(Process $process): stdClass
    {
        $result = Structure::vars(parent::toObject($process));

        // у схемы без type флаг nullable в 3.0 не действует: null живёт только в перечне
        if ($this->type === null) {
            unset($result['nullable']);
        }

        $values = [];

        foreach ($this->enums->items as $item) {
            $values[] = $item instanceof AbstractValues ? $item->toNative($process) : $item;
        }

        if ($this->nullable) {
            $values[] = null;
        }

        if (count($values) === 1 && $process->version()->isV31()) {
            $result['const'] = $values[0];
        } else {
            $result['enum'] = $values;
        }

        return (object) $this->extensions->appendTo($result);
    }

    /**
     * Проверка значения ограничениями, которые у перечисления остались (например, format).
     */
    protected function assertValue(AbstractValues|bool|float|int|string $value): void
    {
    }

    protected static function show(mixed $value): string
    {
        return JsonValue::key($value) === JsonValue::key(null)
            ? 'null'
            : (string) json_encode($value instanceof AbstractValues ? $value->items : $value);
    }
}
