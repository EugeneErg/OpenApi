<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Reader;

use function in_array;

/**
 * Which kind of value a keyword applies to.
 *
 * Three separate passes need this and nothing else from each other: picking the branch
 * for a schema without `type`, splitting a union of types into branches, and deciding
 * that a keyword beside `enum` asserts nothing. While it lived inside the schema reader,
 * everyone — the enum reader included — had to ask the schema reader for it.
 */
final readonly class Keywords
{
    /**
     * Keyword => the kind of value it applies to. Only the keywords that assert
     * something about one value at a time, so they can be checked against every
     * member of an `enum`.
     */
    public const array KINDS = [
        'minLength' => 'string', 'maxLength' => 'string', 'pattern' => 'string',
        'minimum' => 'number', 'maximum' => 'number', 'multipleOf' => 'number',
        'exclusiveMinimum' => 'number', 'exclusiveMaximum' => 'number',
        'minItems' => 'array', 'maxItems' => 'array', 'uniqueItems' => 'array',
        'minProperties' => 'object', 'maxProperties' => 'object', 'required' => 'object',
    ];

    /**
     * Keywords that apply other schemas to the value. They cannot be checked one value
     * at a time, because what they assert is not about the value but about a subschema.
     */
    public const array APPLICATORS = [
        'allOf', 'anyOf', 'oneOf', 'not', 'if', 'then', 'else', 'discriminator', '$ref', '$dynamicRef',
        'properties', 'additionalProperties', 'patternProperties', 'propertyNames',
        'dependentRequired', 'dependentSchemas', 'unevaluatedProperties',
        'items', 'prefixItems', 'contains', 'minContains', 'maxContains', 'unevaluatedItems',
        'contentMediaType', 'contentEncoding', 'contentSchema',
    ];

    /**
     * Keywords tied to a type that do not assert anything about a single value;
     * `format` is tied to no type at all.
     */
    public const array TYPED = [
        'format' => null, 'items' => 'array', 'prefixItems' => 'array', 'contains' => 'array',
        'minContains' => 'array', 'maxContains' => 'array', 'unevaluatedItems' => 'array',
        'properties' => 'object', 'patternProperties' => 'object', 'propertyNames' => 'object',
        'additionalProperties' => 'object', 'dependentRequired' => 'object', 'dependentSchemas' => 'object',
        'unevaluatedProperties' => 'object',
        'contentEncoding' => 'string', 'contentMediaType' => 'string', 'contentSchema' => 'string',
    ];

    /**
     * The kind of value a keyword applies to; null means it applies to any.
     */
    public static function kindOf(int|string $keyword): ?string
    {
        return self::KINDS[$keyword] ?? self::TYPED[$keyword] ?? null;
    }

    public static function isApplicator(string $keyword): bool
    {
        return in_array($keyword, self::APPLICATORS, true);
    }
}
