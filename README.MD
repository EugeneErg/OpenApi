# eugene-erg/open-api

An object-oriented builder of **OpenAPI 3.0 and 3.1** specifications for PHP 8.3+.

Schemas, parameters, responses and security schemes are described by ordinary PHP objects. When one
and the same object is used in `components` and somewhere else as well, the build turns it into a
`$ref` by itself — there are no references to write by hand. The deduplication works across several
files of a specification too.

## What it can do

- **Type safety.** Whatever can be expressed by a type or an enum is expressed by a type or an enum.
- **Automatic `$ref`s.** Objects are compared by identity rather than by value: the same
  `$userSchema` in ten places gives ten references to one schema.
- **Multi-file specifications.** `components.yaml` + `paths.yaml`, with references such as
  `components.yaml#/components/schemas/User`.
- **Every security scheme**: `apiKey`, `http basic`, `http bearer`, `oauth2` (all four flows),
  `openIdConnect`.
- **Two dialects.** 3.0 and 3.1 differ in how `nullable` and the exclusive bounds are written — the
  package picks the right form itself, and the schema objects stay the same.
- **Named arguments as keys.** `new Schemas(User: $user)` instead of arrays of strings.
- **`format` on any type.** JSON Schema declares it an optional annotation, so `format: uri-map` on
  an object is kept; strings and numbers have an enum of the known values.
- **JSON and YAML** on the way out, with no external dependencies.
- **`x-*` extensions** on every object where the specification admits them.
- **Reading finished specifications** back into objects, with the references pointing at the same
  instances.
- **An invalid specification cannot be built**: PHPStan catches some of the mistakes, the
  constructors the rest.

## Installation

```bash
composer require eugene-erg/open-api
```

## Quick start

```php
<?php

declare(strict_types = 1);

use EugeneErg\OpenApi\Builder;
use EugeneErg\OpenApi\Components;
use EugeneErg\OpenApi\Components\RequestBodies;
use EugeneErg\OpenApi\Components\Responses;
use EugeneErg\OpenApi\Components\Schemas;
use EugeneErg\OpenApi\Info;
use EugeneErg\OpenApi\Openapi;
use EugeneErg\OpenApi\Paths;
use EugeneErg\OpenApi\Serialization\YamlEncoder;

$user = new Schemas\Object\Schema(
    properties: new Schemas\Object\Properties(
        id: new Schemas\Object\Property(
            schema: new Schemas\Integer\Schema(format: Schemas\Integer\Format::Int64),
            required: true,
        ),
        email: new Schemas\Object\Property(
            schema: new Schemas\String\Schema(format: Schemas\String\Format::Email),
            required: true,
        ),
    ),
);

$openapi = new Openapi(
    info: new Info(title: 'Example API', version: '1.0.0'),
    components: new Components(
        schemas: new Schemas\Untyped\Schemas(User: $user),
    ),
    paths: new Paths(...[
        '/users' => new Paths\Path(
            get: new Paths\Operation(
                responses: new Responses(
                    x200: new Responses\Response(
                        description: 'A list of users',
                        content: new RequestBodies\Contents(...[
                            // $user already lies in components, so a $ref stands here
                            'application/json' => new RequestBodies\Content(
                                schema: new Schemas\Array\Schema(items: $user),
                            ),
                        ]),
                    ),
                ),
                id: 'listUsers',
            ),
        ),
    ]),
);

// The array key is the file name. It is what cross-file $refs carry.
$builder = new Builder(...['openapi.json' => $openapi]);

// Option 1: get the stdClass and do as you please with it
$documents = $builder->prepareToSave();           // ['openapi.json' => stdClass]

// Option 2: write it to disk straight away
$builder->save(__DIR__ . '/public/docs');         // public/docs/openapi.json

// Option 3: serialise only, without writing
$contents = $builder->encode();                   // ['openapi.json' => '{ ... }']
```

For YAML the file name comes from the same key, and the format from the encoder:

```php
(new Builder(...['openapi.yaml' => $openapi]))->save(__DIR__ . '/public/docs', new YamlEncoder());
```

> **The file name has to be passed as a key.** `new Builder($openapi)` gives the numeric key `0`
> and, as a consequence, invalid references of the form `0#/components/schemas/User`. The
> constructor rejects such a call with an `InvalidArgumentException`.

## The output format

`Builder` can do both JSON and YAML. Both implementations live in the package; there are no
external dependencies:

```php
use EugeneErg\OpenApi\Serialization\JsonEncoder;
use EugeneErg\OpenApi\Serialization\YamlEncoder;

$builder->save('docs');                      // JSON by default
$builder->save('docs', new YamlEncoder());   // YAML
$builder->save('docs', new JsonEncoder(JSON_UNESCAPED_SLASHES)); // flags of your own
```

The file name is taken from the key passed to `Builder` — the encoder does not change it, so the
extension is your choice: `new Builder(...['openapi.yaml' => $openapi])`. If the project already
has `symfony/yaml` and you would rather use it, implementing `Serialization\EncoderInterface` is
enough.

Quotes in YAML are put conservatively: a superfluous quote is acceptable, a lost type is not. The
response code `"200"` stays a string, so does `3.0.3`, and descriptions in any language are printed
unquoted.

### Brief and verbose

By default the printing is brief: a value that equals its default is not printed — `required: false`
on a parameter or `uniqueItems: false` on an array add nothing. The verbose form writes them out:

```php
$builder->prepareToSave(verbose: true);
$builder->encode(verbose: true);
$builder->save('docs', new YamlEncoder(), verbose: true);
```

It is the same specification, and that is how it is checked: every test document is built verbosely,
read back and built briefly — and the result has to match the brief form from the start. The
third-party validator is run over both variants.

Where a default is not merely a default, the verbose form stays silent:

- `encoding` in 3.1 — an explicit `style`, `explode` or `allowReserved` changes how multipart is
  handled there, so "writing out the default" would mean changing the meaning;
- `exclusiveMinimum` and `exclusiveMaximum` — in 3.0 those are flags beside a bound, and without
  the bound a flag is inadmissible; in 3.1 they are the bound itself, and there is nothing to write
  out in it;
- `nullable` in 3.1 — it does not exist there at all, `null` is added as a second type.

## What the build checks

Some of the mistakes are caught statically: the non-negative bounds are declared as `int<0, max>`,
so PHPStan will not let `minLength: -5` through. The rest is checked in the constructors:

- a path that does not start with a slash, or `{id}` occurring twice in a template
- a template that has `{id}` but no matching path parameter — or the other way round
- an operation without a single response (in 3.0 `responses` are required, in 3.1 they may be left out)
- contradictory enumerations — see "Enumerations"
- a lower bound above the upper one, a `multipleOf` that is not positive
- a `discriminator` without `oneOf` / `anyOf` / `allOf`
- `value` and `externalValue`, or `example` and `examples`, given at once

Whatever the types express is simply absent from the API: a path parameter has no `allowEmptyValue`
and no `allowReserved` (those are for query only), and its `required` is always `true`.

### And where exactly

What refuses is always the deepest object: it knows what it is missing but not where it lies — and
the call stack shows the insides of the package rather than the document. So the place is gathered on
the way up: every container adds one step of its own.

```
openapi.json/paths/~1users~1{id}/get/responses/200/content/application~1json/schema:
An array schema must declare "items" in OpenAPI 3.0; it is optional only since 3.1.
```

The pointer is written as the reader writes it — by RFC 6901 — so the path template `/users/{id}`
looks like `~1users~1{id}` inside it. The first step is the file name: with several documents there
is no telling which of them is at fault without it. The place is separately available as
`$exception->place()` on any exception of the package.

An object that refused inside its constructor (inverted bounds, a `then` without an `if`) does not
lie in any document yet — there `place()` returns `null`, and the call stack shows the place: that
is a line of your code rather than a line of the specification.

## Enumerations

A closed set of values is described by a schema of its own — the `EnumSchema` of its type:

```php
$status = new Schemas\String\EnumSchema(
    new Schemas\String\Strings('success', 'failure', 'neutral'),
    nullable: true,
    format: 'check-conclusion',
    default: new Schemas\String\Value('neutral'),
);
$kind = new Schemas\String\EnumSchema(new Schemas\String\Strings('payment_intent'));
$deleted = new Schemas\Boolean\EnumSchema(true);
$size = new Schemas\Untyped\EnumSchema(new Schemas\Untyped\Values('auto', 0));
```

Why not an `enum` parameter on an ordinary schema. Once the values are listed, any keyword that
checks something (`minLength`, `pattern`, `minimum`, `items`, `properties`, `allOf`, `not`…) either
holds for every value and changes nothing, or cuts some of them off — and then it is a mistake.
There is no third case. So `EnumSchema` has no such parameters: the useless cannot be written, and
nothing useful is lost. What is left are the annotations — `title`, `description`,
`readOnly`/`writeOnly`, `deprecated`, `xml`, `format`, `default` and the 3.1 identifiers. `example`
is gone for the same reason: on an enumeration it adds nothing.

What the package does by itself:

- **`const`.** A single value is printed as `const` in 3.1 and as a one-element `enum` in 3.0. There
  is no separate `const` parameter: it is the same enumeration.
- **`nullable`.** `null` is added both to the flag (the type) and to the list itself: by 3.0.3 null
  is inadmissible without that. Writing `null` among the values is not needed — it is an error.
- **The checks.** An empty list is rejected, and so are repeats (`1` and `1.0` are one value, and the
  order of an object's keys does not matter), a `default` outside the list, and values that do not
  fit a known `format` (`date`, `date-time`, `email`, `uuid`, `ipv4`, `ipv6`, `byte`, `int32`,
  `float`).
- **The type.** For values of one type there is only the typed schema; `Untyped\EnumSchema` accepts
  mixed sets alone. `Boolean\EnumSchema` accepts a single value: a list of `true` and `false`
  restricts nothing.

When an enumeration does have to be combined with a composition, that is equivalent to an `allOf`:

```php
$deletedDog = new Schemas\Untyped\Schema(allOf: new Schemas\Untyped\Schemas(
    $dog,
    new Schemas\Object\EnumSchema(new Schemas\Object\Objects(
        new Schemas\Object\OpenapiObject(deleted: true),
    )),
));
```

Reading a finished document, an enumeration is taken apart by its meaning. `example` and the
keywords that do not apply to the type are dropped. The simple checks (`maxLength`, `pattern`, the
bounds, `required`…) are applied to every value: when all of them pass, the keyword is dropped; when
they do not, the document is rejected, because some of the values are unreachable. Everything else is
kept without a loss, through `allOf`. A `nullable: true` without a `null` in the list is read as
nullable — which is how it is written in practice.

## Extensions

Almost every object of the specification has `x-*` fields. The prefix is always added, so the name is
written without it:

```php
use EugeneErg\OpenApi\Extensions;

new Schemas\String\Schema(extensions: new Extensions(internal: true));
```

That gives `{"type": "string", "x-internal": true}`. A name with a hyphen cannot be written as a
named argument, so it is passed by unpacking. The two cannot be mixed: PHP forbids unpacking after
named arguments, so it is either all named arguments or all unpacking:

```php
new Extensions(...[
    'internal' => true,
    'code-samples' => new Schemas\Array\OpenapiArray('curl …'),
]);
```

- The rule is one and has no exceptions, so every field of the document has exactly one spelling:
  `x-legacy` gives `x-x-legacy`, which the specification allows (of the name it asks only that it
  start with `x-`). The other side of it: the prefix is never written by hand — written, it doubles.
- The 3.1 specification reserved the `x-oai-` and `x-oas-` prefixes for the OpenAPI Initiative. The
  package accepts them — a document with them is legal — but an extension of your own is better named
  otherwise.
- A value is any JSON value; objects and lists are given by the same `OpenapiObject` and
  `OpenapiArray` as the examples.
- Names such as `7` are passed through `Extensions::fromArray()`, as in every other map.

Three objects that look like ordinary maps have extensions as well: the Paths Object, an operation's
Responses Object and the Callback Object. Their extensions are passed as the second argument of
`fromArray()`:

```php
Paths::fromArray(
    ['/users' => new Paths\Path(get: $onUserCreated)],
    new Extensions(section: 'users'),
);
```

`webhooks` and the sections of `components`, on the other hand, the specification declares as
`Map[string, …]`: extensions there belong not to the map but to the object itself (`Openapi` and
`Components`), and an attempt to set them is rejected. An `x-…` name in such a map is the name of a
component rather than an extension: in GitHub's `components.headers` that is the name of the
`x-common-marker-version` header, and it is read as exactly that — a component.

## Response codes

A named argument in PHP cannot start with a digit, so the HTTP codes are written with an `x` prefix,
which the build takes off:

```php
new Responses(
    x200: new Responses\Response(description: 'OK'),
    x404: new Responses\Response(description: 'Not found'),
    x4XX: new Responses\Response(description: 'Client error'),  // a wildcard works too
    default: $generalError,
);
```

On an operation a response key has to be a code or `default`: `new Responses(ok: …)` will not build.

## The names '7', '-1' and fromArray()

The maps (`Properties`, `Examples`, `Contents`, `Schemas` and the rest) take names as named arguments
or by unpacking an array. Unpacking has one PHP trap: the key `'7'` or `'-1'` is stored as an integer
and turns into a positional argument — the name would quietly be gone. So an item of a map without a
name is an error, and `fromArray()` is there for any names at all:

```php
$reactions = Schemas\Object\Properties::fromArray([
    '+1' => new Schemas\Object\Property(new Schemas\Integer\Schema()),
    '-1' => new Schemas\Object\Property(new Schemas\Integer\Schema()),
]);
$example = Schemas\Object\OpenapiObject::fromArray(['0' => 'first', '1' => 'second']);
```

The lists (`Strings`, `Tags`, `Servers`, `allOf`…) reject names instead — they would be gone when
written. `Schemas` serves both: `components.schemas`, `mapping` and `$defs` require names, while
`allOf`, `anyOf`, `oneOf` and `prefixItems` require their absence.

The one place where an item of a map may be passed without a name is an operation's parameters: a
parameter registered in `components.parameters` takes its name from the registration and is printed
as a `$ref`. An unregistered nameless parameter the build rejects.

## Required fields outside properties

A described property is made required by the `Property(required: true)` flag. The names that
`properties` does not describe — their shape comes from `additionalProperties`, `patternProperties`
or a composition — are listed in the schema's own `required`:

```php
// "exactly one of the two fields is needed"
$target = new Schemas\Untyped\Schema(oneOf: new Schemas\Untyped\Schemas(
    new Schemas\Object\Schema(declareType: false, required: new Strings('id')),
    new Schemas\Object\Schema(declareType: false, required: new Strings('owner', 'repo')),
));
```

A name out of `properties` is rejected here (the flag is there for it), and so is a name that, with
`additionalProperties: false`, matches no `patternProperties`: such an object is impossible.

## Several files

Return several documents from the build — the references between them fall into place by themselves:

```php
$components = new Openapi(info: $info, components: new Components(schemas: $schemas));
$paths = new Openapi(info: $info, paths: $paths);

(new Builder(...[
    'components.yaml' => $components,
    'paths.yaml' => $paths,
]))->prepareToSave();
```

In `paths.yaml` the references take the form `components.yaml#/components/schemas/User`.

## Security

```php
use EugeneErg\OpenApi\Components\SecuritySchemes;
use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security;
use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows\AuthorizationCodeFlow;
use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows\Scope;
use EugeneErg\OpenApi\Components\SecuritySchemes\Oauth2Security\Flows\Scopes;
use EugeneErg\OpenApi\Securities;

$readPets = new Scope('Read your pets');
$writePets = new Scope('Modify your pets');

$oauth = new Oauth2Security\Scheme(
    flows: Oauth2Security\Flows::createAuthorizationCode(
        new AuthorizationCodeFlow(
            authorizationUrl: 'https://example.com/oauth/authorize',
            tokenUrl: 'https://example.com/oauth/token',
            // scope names with a colon cannot be passed as named arguments
            scopes: new Scopes(...[
                'read:pets' => $readPets,
                'write:pets' => $writePets,
            ]),
        ),
    ),
);

$openapi = new Openapi(
    info: $info,
    components: new Components(
        securitySchemes: new SecuritySchemes(petstoreAuth: $oauth),
    ),
    // the Scope object finds its own scheme and its own name
    security: new Securities(new Securities\SecuritySchemes($readPets)),
);
```

That gives `security: [{ "petstoreAuth": ["read:pets"] }]`.

The other schemes:

```php
new SecuritySchemes\ApiKeySecurity\Scheme(name: 'X-Api-Key', in: SecuritySchemes\ApiKeySecurity\In::Header);
new SecuritySchemes\BasicHttpSecurityScheme();
new SecuritySchemes\BearerHttpSecurityScheme(format: 'JWT');
new SecuritySchemes\HttpSecurityScheme('Digest');   // any IANA HTTP scheme except basic and bearer
new SecuritySchemes\OpenIdConnectSecurityScheme(openIdConnectUrl: 'https://example.com/.well-known/openid-configuration');
```

The oauth2 scopes are declared in the scheme itself, so a `Scope` object is what is passed: its name
and its scheme come from the declaration and cannot drift apart. The specification does not require a
scope to be declared — with openIdConnect the provider publishes the list, and an oauth2 document may
require a scope that no flow has. Such a scope is named by its name: `Securities\ScopeName`. The
other schemes may require roles in 3.1 — `Securities\Role`; in 3.0 their list has to be empty, and
the build checks that.

```php
$bearer = new SecuritySchemes\BearerHttpSecurityScheme();
$oidc = new SecuritySchemes\OpenIdConnectSecurityScheme('https://example.com/.well-known/openid-configuration');

new Securities(
    new Securities\SecuritySchemes(new Securities\Role($bearer, 'admin')),     // 3.1 only
    new Securities\SecuritySchemes(new Securities\ScopeName($oidc, 'openid')),
    new Securities\SecuritySchemes(),   // {} — anonymous access is allowed
);
```

On an operation `security: null` (the default) means "as the document has it", and
`new Securities()` means "the operation is open", even when the document requires authorisation.

## The document's version

By default `3.0.3` is built. The version is set through the `Version` enum:

```php
use EugeneErg\OpenApi\Version;

new Openapi(info: $info, version: Version::V310);
```

One and the same schema objects give a different output:

| | 3.0 | 3.1 |
|---|---|---|
| `new Schemas\String\Schema(nullable: true)` | `{"type": "string", "nullable": true}` | `{"type": ["string", "null"]}` |
| `new Schemas\Number\Schema(minimum: 0, exclusiveMinimum: true)` | `{"minimum": 0, "exclusiveMinimum": true}` | `{"exclusiveMinimum": 0}` |
| `new Schemas\String\EnumSchema(new Schemas\String\Strings('a'))` | `{"type": "string", "enum": ["a"]}` | `{"type": "string", "const": "a"}` |

`webhooks` exist in 3.1 only — an attempt to set them for 3.0 makes the constructor throw an
`InvalidArgumentException`:

```php
new Openapi(
    info: $info,
    version: Version::V310,
    webhooks: new PathItems(...[
        'userCreated' => new Paths\Path(post: $onUserCreated),
    ]),
);
```

## Servers

```php
use EugeneErg\OpenApi\Components\Schemas\String\Strings;
use EugeneErg\OpenApi\Servers;

new Servers(
    new Servers\Server(
        url: 'https://{region}.api.example.com/{ver}',
        variables: new Servers\Variables(
            region: new Servers\Variable(default: 'eu', enum: new Strings('eu', 'us')),
            ver: new Servers\Variable(default: 'v1'),
        ),
    ),
);
```

## Polymorphism

```php
use EugeneErg\OpenApi\Components\Schemas\Abstract\Discriminator;

$pet = new Schemas\Untyped\Schema(
    oneOf: new Schemas\Untyped\Schemas($dog, $cat),
    discriminator: new Discriminator(
        propertyName: 'petType',
        mapping: new Schemas\Untyped\Schemas(dog: $dog, cat: $cat),
    ),
);
```

The schemas out of `mapping` have to be registered in `components.schemas`, or the build fails with
`ComponentsNotFoundOpenapiException`.

## Exceptions

Every exception of the package implements `EugeneErg\OpenApi\Exceptions\OpenapiExceptionInterface`:

| Exception | When |
|---|---|
| `ScopeNotFoundOpenapiException` | a `Scope` is declared in no oauth2 flow of the document |
| `SecuritySchemeNotFoundOpenapiException` | the scheme is not registered in `components.securitySchemes` |
| `OperationNotFoundOpenapiException` | a `Link` refers to an operation that is not in `paths` |
| `ComponentsNotFoundOpenapiException` | a `$ref` is needed, but the object lies in no `components` |
| `InvalidPathOpenapiException` | a path template disagrees with the path parameters |
| `InvalidSchemaOpenapiException` | a schema's bounds or keywords disagree with each other |
| `InvalidArgumentOpenapiException` | the remaining violations of the specification's invariants |

## Reading a finished specification

The task the other way round: take an existing document apart into objects, change it and write it
back.

```php
use EugeneErg\OpenApi\Reader;
use EugeneErg\OpenApi\Serialization\YamlDecoder;

$openapi = Reader::read(file_get_contents('openapi.json'));

// several files go in by key, as in Builder: the keys are what resolve the cross-file references
$documents = Reader::readAll([
    'components.yaml' => file_get_contents('components.yaml'),
    'paths.yaml' => file_get_contents('paths.yaml'),
], new YamlDecoder());
```

The property that matters most: **one and the same reference gives one and the same object**. A
schema that a `$ref` points at from three places will be one instance — and writing it back turns
that into three references again rather than three copies. Recursive schemas are restored as a real
cycle, through `DeferredSchema`.

A document is read by its meaning rather than word for word. Whatever affects nothing is dropped, and
the equivalent forms are brought to one:

- the values that equal their default, and the empty lists and maps;
- an `example` that does not fit the type of its schema;
- `encoding` on content other than forms and multipart;
- the siblings of a `$ref` in 3.0 (they do not apply there). In 3.1 `$ref` is an ordinary keyword, and
  `{$ref, description}` is read as `{allOf: [$ref], description}`; a schema of a single `allOf`
  becomes that member itself again when written;
- what enumerations do — see "Enumerations".

A component that merely refers to another one (`UserBase: {$ref: UserCompact}`) stays an object of its
own: the references to `UserBase` will not wander off to `UserCompact`. A `null` in `example`,
`default` and `value` is kept — that is a value, not the absence of one.

A document that contradicts itself is rejected with the place named: a requirement for an oauth2
scope that no flow of the scheme declares, say.

### Strict reading

Everything listed above the package drops **knowing why**. But a document may also carry a field the
specification does not define at all — and that one cannot be dropped in silence: a document is read
in order to be changed and written back, and such a field would disappear without a trace. So reading
is strict by default and names every such place:

```php
Reader::read($content);                      // names what it did not understand and refuses to read
Reader::read($content, strict: false);       // reads, dropping what it did not understand
```

```
The specification does not define these fields: openapi.json/info/slogan,
openapi.json/components/securitySchemes/bearer/in. Pass strict: false to read the document without them.
```

The second place here comes from the real Immich document: NestJS writes `in` and `name` on a scheme
of type `http`, while the "Applies To" table in the specification declares them for `apiKey` only.
The document does not break because of it, and `strict: false` reads it whole — but now it is known,
rather than "somehow gone by itself".

What was read and dropped does not count as misunderstood in strict mode: `uniqueItems` on a string,
the siblings of a `$ref` in 3.0, `encoding` on JSON, an example beside an enumeration — all of that
the package took apart and knows that it changes nothing.

Parsing JSON is built in. YAML needs `ext-yaml`, or a dozen lines of your own over
`DecoderInterface`. `YamlDecoder` reads by the YAML 1.2 core schema, as OpenAPI requires: by default
ext-yaml follows YAML 1.1 and turns `no` and `on` into booleans, and `12:30` and `1,5` into numbers.
One limitation: `1e3` and `0o17` ext-yaml hands over as a string before the parsing even starts, so
they are better avoided in the numeric keywords.

A decoder of your own over another parser:

```php
use EugeneErg\OpenApi\Serialization\DecoderInterface;
use EugeneErg\OpenApi\Serialization\Structure;
use Symfony\Component\Yaml\Yaml;

final readonly class SymfonyYamlDecoder implements DecoderInterface
{
    public function decode(string $content): \stdClass
    {
        return Structure::toObject(Yaml::parse($content));
    }
}
```

## How it works

`Builder` keeps the documents and knows their file names. All the work with references is done by
`Process` — the context in which one document is built. Every `toObject()` is handed a `Process` and
asks it: is this object already sitting in somebody's `components`? If it is, a `$ref` stands in its
place — local, or carrying the name of a neighbouring file; if it is not, the object is written out
where it is used. The document's version is available through `Process` as well, which is how the
schemas can print themselves in both dialects.

### How to read the class names

Every namespace of a type holds four things, and their endings tell them apart:

| Name | What it is | Example |
| --- | --- | --- |
| `Schema`, `EnumSchema` | a schema | `String\Schema`, `String\EnumSchema` |
| `Schemas` | a set of **schemas** — `allOf`, `$defs`, `components.schemas` | `Untyped\Schemas` |
| `Value` | one **value**: `default`, `example` | `String\Value('a')` |
| the plural of the type | a set of **values**: the members of an enumeration | `String\Strings`, `Integer\Integers`, `Object\Objects` |

That is, `Schemas` is about schemas, while `Strings`, `Integers`, `Numbers`, `Objects` and `Arrays`
are about values. Values of mixed types have no plural of their own, so they are called
`Untyped\Values`.

`Object\OpenapiObject` and `Array\OpenapiArray` stand apart — they are the JSON literals: the object
and the list that values and extensions are made of. The prefix is not there out of spite: `Object`
and `Array` are reserved words in PHP, and a class cannot be named that.

The schema reader has three neighbours to help it: `ValueReader` takes values apart, `EnumReader` the
enumerations, and `Keywords` knows which type of value a keyword applies to.

## JSON Schema 2020-12

In 3.1 a schema is a full JSON Schema. The whole vocabulary is supported: `const`,
`if`/`then`/`else`, `prefixItems`, `contains`, `patternProperties`, `propertyNames`,
`dependentRequired`, `dependentSchemas`, `unevaluated*`, `content*`, `$defs`, `$id`, `$anchor`,
`$vocabulary`, `$schema`.

### A schema as a resource

The words of the core vocabulary — `$id`, `$schema`, `$vocabulary`, `$anchor`, `$dynamicAnchor`,
`$dynamicRef`, `$defs`, `$comment` — are passed as one `Resource` object:

```php
new Schemas\Object\Schema(resource: new Schemas\Abstract\Resource(
    id: 'https://example.com/schemas/node',
    schema: 'https://json-schema.org/draft/2020-12/schema',
));
```

They are gathered together not because they are rarely needed but because the specification declares
them together: these are words not about the value but about the schema itself — how to address it
and by which rules to read it. Hence the shared rules, which `Resource` now checks itself: a dialect
and a vocabulary are admissible at the root of a resource only, that is, beside an `$id`, and in 3.0
the whole group is absent.

Addressing stays by objects — raw reference strings are not to be written anywhere. A schema that
lies in `$defs` is referred to just as an ordinary component is:

```php
$street = new Schemas\String\Schema(minLength: 1);

$user = new Schemas\Object\Schema(
    properties: new Schemas\Object\Properties(
        road: new Schemas\Object\Property(schema: $street),
    ),
    resource: new Schemas\Abstract\Resource(defs: new Schemas\Untyped\Schemas(Street: $street)),
);
// road => {"$ref": "#/components/schemas/User/$defs/Street"}
```

`$dynamicRef` takes an object as well rather than a string: the builder takes its `$dynamicAnchor`
itself.

```php
$node = new Schemas\Object\Schema(resource: new Schemas\Abstract\Resource(dynamicAnchor: 'node'));
new Schemas\Untyped\Schema(resource: new Schemas\Abstract\Resource(dynamicRef: $node));
// => {"$dynamicRef": "#node"}
```

A dynamic anchor is found by name rather than by pointer, and that has two consequences. A target in
a neighbouring file is written with that file — `other.yaml#node` — and a target that no document
declares in `components.schemas` is refused at build time: written out in place, it has no name, and
the reference would lead nowhere.

## Several types and the null type

In 3.1 `type` may be an array. A `null` in it is `nullable`, and it is read as before:

```php
new Schemas\String\Schema(nullable: true);   // 3.1: {"type": ["string", "null"]}
```

A schema that has no other types at all is `{"type": "null"}`: a single value is admissible. It has a
schema of its own, and in 3.0, where there is no such type, the build rejects it:

```php
new Schemas\Null\Schema(description: 'Always null.');
```

A union of several types the package describes by the type of every variant, so
`{"type": ["string", "integer"]}` is an `anyOf` of two schemas:

```php
new Schemas\Untyped\Schema(anyOf: new Schemas\Untyped\Schemas(
    new Schemas\String\Schema(maxLength: 5),
    new Schemas\Integer\Schema(minimum: 0),
));
```

Reading the compact form, the package lays it out the same way: the keywords that apply to one of the
listed types go into its branch, and the common ones stay outside, through `allOf`.

### A check without a declared type

`{"minLength": 3}` does not mean "a string longer than two": a value of any other type fits such a
schema, because a check on strings does not apply to it. So a check may be written without declaring
a type — `declareType: false` on the schema of the type in question:

```php
new Schemas\String\Schema(minLength: 3, declareType: false);   // {"minLength": 3}
new Schemas\Object\Schema(properties: $properties, declareType: false);
```

The other way round: with a type declared, a keyword about values of another type means nothing —
`uniqueItems` on a string always holds, because a string is not an array. Such a keyword the package
does not print (see "What the build checks", the third principle), and reading drops it:
kube-openapi writes `uniqueItems: true` on every query parameter, and the Kubernetes document has
550 such places.

## References to operations, and pagination

A `Link` points at an operation by the object rather than by a name: an operation that does not exist
cannot be referred to that way. But an operation that refers to itself — the "next page" — cannot be
passed to its own constructor, so the reference is deferred by a closure, as with recursive schemas:

```php
$listUsers = new Paths\Operation(
    responses: new Responses(x200: new Responses\Response(
        description: 'A page of users',
        links: new Links(next: new Link(
            operation: new DeferredOperation(static function () use (&$listUsers): Paths\Operation {
                return $listUsers;
            }),
            parameters: new Link\Parameters(page: Link\Parameter::expression('$response.body#/next')),
        )),
    )),
    id: 'listUsers',
);
```

An operation that has an `operationId` is printed by it, and one without by a pointer to its place
(`operationRef: "#/paths/~1users/get"`). A Path Item Object lives in four places — `paths`,
`webhooks`, `components.pathItems` and a Callback Object — and an operation in any of them is a
target a Link may name: `operationId` "MUST be resolved within the scope of the OpenAPI
Description", not within `paths`. When the target operation is in none of the documents handed to
`Builder`, the build rejects that — including when the link names it by `operationId`, where the
written document itself says nothing about where that operation is.

## References with a description of their own

Usually there is no reference to make: pass the object, and the builder puts the `$ref` in itself. A
Reference Object is needed only where the reference has to override the component's description (in
3.1 it has a `summary` and a `description` of its own):

```php
use EugeneErg\OpenApi\Reference;

new Responses(
    x404: new Reference($notFound, description: 'The user was not found.'),
    x500: $notFound,   // without an override, just the object
);
```

That gives `{"$ref": "#/components/responses/NotFound", "description": "..."}` and a bare `$ref`
respectively. The target is given by the same object, so there is still one way to refer to something
in this package.

A request body, a parameter and a callback are referred to the same way:

```php
new Paths\Operation(
    responses: $responses,
    requestBody: new Reference($upload, description: 'The user to create.'),
    callbacks: new Components\Callbacks(onCreated: $onEvent),
    parameters: new Parameters\Parameters(
        // the parameter's name comes from the component's declaration, so it is not passed here
        queries: new Parameters\Query\Queries(new Reference($pageQuery, description: 'A description of its own.')),
    ),
);
```

An operation's list of parameters is checked for repeats: the specification counts a parameter unique
by the pair "name + location", and a second `page` in query is rejected — even when the first is
passed by name and the second as a reference.

## Limitations

- By the specification a `Security Requirement Object` refers to the schemes of its own document, so
  the scopes are not looked up in the neighbouring files. That is a limitation of the specification
  rather than of the package.
- The invariants of the specification are checked rather than the meaning of the document: the
  package will catch an `{id}` without a parameter or inverted bounds, but it will not tell you that
  a schema describes something other than what the server returns.

## Development

```bash
composer install
composer check     # php-cs-fixer + phpstan (the maximum level) + phpunit
```

CI runs the same on PHP 8.3 and 8.4 with `ext-yaml` — plus `composer real-world` and
`composer validate-output` as a separate job: those reach for the network and for Node.js, and their
failure should not look like a failure of the tests. PHP 8.5 is run as well, as a job that does not
gate the build: `composer.json` allows `^8.3`, so the runtime has to be tried somewhere, but a
deprecation raised inside php-cs-fixer or PHPStan on a runtime newer than they support is not a
defect of this package.

`composer coverage` prints which lines the suite never executed (it needs pcov or Xdebug; the test
jobs deliberately run without a driver). The number itself is not a target — the point is to look at
the untouched lines.

The tests are arranged as pairs of files: `tests/Cases/<Test>/Objects/<name>.php` returns the
objects, and `tests/Cases/<Test>/Jsons/<name>.json` the expected result. New cases are picked up
automatically; there is no need to register them in the test.

`ReadmeTest` separately executes every PHP example in this file, so the documentation cannot drift
away from the code unnoticed.

`RoundTripPropertyTest` does the same round trips on documents nobody wrote: `RandomDocument`
combines the objects of the package at random — one file and several, both versions, JSON and YAML,
brief and verbose — and the properties are stated as laws rather than as expected texts. Building
has to be idempotent: what is read from a built document and built again has to come out the same
text. The generator carries its own xorshift, so a failure names a seed that reproduces it. Two
defects were found this way straight away: a Link naming an operation that lives in `webhooks`, and
a `$dynamicRef` whose anchor is in a neighbouring file.

`RealWorldTest` runs the full read → write round trip on real specifications
(`tests/Cases/RealWorldTest`; the sources are in `SOURCES.md`) and compares the meaning rather than
the text. Everything that counts as an insignificant difference is listed in one place —
`tests/Support/SemanticDiff.php`.

The large public specifications are too big for the repository, and a separate command checks those:

```bash
composer real-world             # seventeen documents
composer real-world -- discord  # a selection
```

Besides the large public APIs (GitHub in 3.0 and 3.1, Stripe, Twilio, Asana, Discord) it deliberately
gathers documents from different generators: FastAPI (Airflow), NestJS (Immich), drf-spectacular
(authentik), go-swagger (Ory Kratos), kube-openapi (Kubernetes), .NET (Sonarr), Go (Hyperledger
FireFly), Spring Boot (Camunda 8), openapi-extractor for PHP (Nextcloud), the Elasticsearch
specification compiler and Redocly's example. Each has habits of its own, and almost every one of
them brought a bug along.

Two documents on that list the specification does not admit, and the package rejects them: Sonarr has
a path parameter that the path template does not carry, Kratos a security requirement with an
undeclared scheme. They cannot be read (the package's objects do not express such a thing), and
quietly fixing them would be a lie, so what is checked is the refusal itself and its wording: the
wording is what whoever brings such a document gets.

The report separately counts the places where the package chose an equivalent form over the original
(a union of types becomes an `anyOf`, an enumeration beside an applicator becomes an `allOf`).
Repeating that layout inside the check is out of the question — it would compare the code with itself
— so an independent condition is checked there: that not a single value was lost.

The read → write round trip proves the document was not lost, but not that it is valid: the package
could read and write in the same wrong way. That is exactly how a parameter with `content` lived for
a while — the content type was printed as a `mimeType` field instead of a key inside `content`, and
the reader read that mistake back. So the output is checked by a third-party validator:

```bash
composer validate-output        # Node.js is needed; Redocly is downloaded through npx
```

The command builds every `BuilderTest` case in JSON **and** in YAML — the YAML encoder is our own,
and its mistakes are invisible in JSON (that is how the empty maps inside a sequence were found) —
and runs Redocly's structural rules. Among the cases are `kitchen-sink-30` and `kitchen-sink-31`: one
document per version, holding every object of the specification.

The places where the validator itself departs from the specification are listed in `KNOWN_DEFECTS`
inside `tools/validate-output.php` — every one of them checked against the text of the specification:

- Redocly expects a string from `$vocabulary`, while JSON Schema 2020-12 declares it an object of
  URI => bool.
- Spectral (`oas3-schema`) carries the OAS 3.1.0 schema, which has no `$ref` on a Path Item (the
  3.1.1 text does have it) and has a typo in the Link Object, `body` instead of `server`.

A check that fails on somebody else's mistakes is worse than no check at all, so such a list is kept
explicitly and with an explanation rather than silenced wholesale.

## Licence

MIT. See [LICENSE](LICENSE).
