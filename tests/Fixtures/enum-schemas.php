<?php

declare(strict_types = 1);

use EugeneErg\OpenApi\Components\Schemas;

/*
 * Одни и те же перечисления для 3.0 и 3.1: форма вывода выбирается версией.
 * Значения взяты из реальных спецификаций (Stripe, GitHub, Twilio).
 */
return new Schemas\Untyped\Schemas(
    // Stripe: поле-дискриминатор объекта — единственное значение
    ObjectKind: new Schemas\String\EnumSchema(
        new Schemas\String\Strings('payment_intent'),
        description: 'String representing the object\'s type.',
    ),
    // GitHub: nullable-перечисление — null попадает и во флаг, и в перечень
    Conclusion: new Schemas\String\EnumSchema(
        new Schemas\String\Strings('success', 'failure', 'neutral', 'cancelled'),
        nullable: true,
        access: Schemas\Abstract\Access::ReadOnly,
    ),
    // Twilio: format остаётся аннотацией и у перечисления
    HttpMethod: new Schemas\String\EnumSchema(
        new Schemas\String\Strings('GET', 'POST'),
        format: 'http-method',
        default: new Schemas\String\Value('POST'),
    ),
    // Stripe: deleted-объекты помечены единственным true
    Deleted: new Schemas\Boolean\EnumSchema(true, description: 'Always true for a deleted object.'),
    Priority: new Schemas\Integer\EnumSchema(
        new Schemas\Integer\Integers(1, 2, 3),
        format: Schemas\Integer\Format::Int32,
    ),
    Ratio: new Schemas\Number\EnumSchema(new Schemas\Number\Numbers(0.5, 1, 2)),
    // значения разных типов — только так
    Size: new Schemas\Untyped\EnumSchema(new Schemas\Untyped\Values('auto', 0), nullable: true),
    Origin: new Schemas\Array\EnumSchema(new Schemas\Array\Arrays(
        new Schemas\Array\OpenapiArray(0, 0),
        new Schemas\Array\OpenapiArray(1, 1),
    )),
    Unit: new Schemas\Object\EnumSchema(new Schemas\Object\Objects(
        new Schemas\Object\OpenapiObject(code: 'kg', factor: 1),
    )),
);
