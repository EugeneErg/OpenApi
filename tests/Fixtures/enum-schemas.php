<?php

declare(strict_types = 1);

use EugeneErg\OpenApi\Components\Schemas;

/*
 * The same enumerations for 3.0 and 3.1: the version picks the printed form.
 * The values are taken from real specifications (Stripe, GitHub, Twilio).
 */
return new Schemas\Untyped\Schemas(
    // Stripe: an object's discriminator field — a single value
    ObjectKind: new Schemas\String\EnumSchema(
        new Schemas\String\Strings('payment_intent'),
        description: 'String representing the object\'s type.',
    ),
    // GitHub: a nullable enumeration — null goes into the flag and into the list alike
    Conclusion: new Schemas\String\EnumSchema(
        new Schemas\String\Strings('success', 'failure', 'neutral', 'cancelled'),
        nullable: true,
        access: Schemas\Abstract\Access::ReadOnly,
    ),
    // Twilio: format stays an annotation on an enumeration as well
    HttpMethod: new Schemas\String\EnumSchema(
        new Schemas\String\Strings('GET', 'POST'),
        format: 'http-method',
        default: new Schemas\String\Value('POST'),
    ),
    // Stripe: the deleted objects are marked by a single true
    Deleted: new Schemas\Boolean\EnumSchema(true, description: 'Always true for a deleted object.'),
    Priority: new Schemas\Integer\EnumSchema(
        new Schemas\Integer\Integers(1, 2, 3),
        format: Schemas\Integer\Format::Int32,
    ),
    Ratio: new Schemas\Number\EnumSchema(new Schemas\Number\Numbers(0.5, 1, 2)),
    // values of different types — this is the only way
    Size: new Schemas\Untyped\EnumSchema(new Schemas\Untyped\Values('auto', 0), nullable: true),
    Origin: new Schemas\Array\EnumSchema(new Schemas\Array\Arrays(
        new Schemas\Array\OpenapiArray(0, 0),
        new Schemas\Array\OpenapiArray(1, 1),
    )),
    Unit: new Schemas\Object\EnumSchema(new Schemas\Object\Objects(
        new Schemas\Object\OpenapiObject(code: 'kg', factor: 1),
    )),
);
