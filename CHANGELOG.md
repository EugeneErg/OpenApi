# Changelog

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the versions
follow [semver](https://semver.org/).

## [3.0.0] — unreleased

A major release: the signatures of the enumerations and of `Operation` have changed. Everything was
found by two checks: running real specifications through the read → write → compare round trip
(seventeen documents, from the OAI examples to Kubernetes, Elasticsearch and Camunda 8) and a
third-party validator of the output — the round trip also closes when the package reads and writes
in the same wrong way.

### Changed (incompatibly)

- **The enumerations were reworked.** Only the annotations are left on `*\EnumSchema`: a checking
  keyword beside `enum` either changes nothing or makes some of the values unreachable. Added:
  `format` (strings and numbers), `content*` (strings), `$comment`, `$defs`, `$id`, `$anchor`,
  `$dynamicAnchor`, `$vocabulary`.
- **`const` was removed from the ordinary schemas.** A single value is an `EnumSchema` with one
  member; in 3.1 it is printed as `const`, in 3.0 as an `enum`.
- `Boolean\EnumSchema` takes one `bool $value`; `null` ("both values") is no longer accepted — that
  is simply `Boolean\Schema`.
- `Operation::$security` became `?Securities`: `null` means inherit, `new Securities()` means open.
- `Operation::$responses` became `?Responses`: in 3.1 the responses are optional, in 3.0 their
  absence is still an error (at build time).
- **The maps reject items without a name**, and the lists items with one. Before, the key `'7'`
  quietly became a position when unpacked, and names in lists quietly disappeared. `::fromArray()`
  was added for any names at all.
- `Schemas` is checked at the place where it is used: `components.schemas`, `mapping` and `$defs`
  take names only; `allOf`, `anyOf`, `oneOf` and `prefixItems` take the absence of names only.
- A nameless parameter of an operation has to be registered in `components.parameters`; such
  parameters lie in `AbstractParameters::$registered`.
- `Encoding`: `explode`, `allowReserved` and `style` became nullable and are printed only when they
  are set — in 3.1 giving them explicitly changes how multipart is handled.
- `Contents` rejects `encoding` on types other than forms and `multipart/*`.
- The keys of an operation's responses are checked: a code, a pattern such as `4XX`, or `default`.
- The names of the components are checked against `^[a-zA-Z0-9._-]+$`.
- `YamlDecoder` reads by the YAML 1.2 core schema.
- `Parameters\Path\Style::matrix` was renamed to `Style::Matrix` — like its neighbours `Label` and
  `Simple`.
- `Operation::$requestBody` and the containers of an operation's parameters accept a `Reference`, so
  their types were widened.
- **`required: false` on a path parameter is rejected**: "If the parameter location is "path" … its
  value MUST be true". Before, the keyword was not read at all.
- **An operation's list of parameters is checked for repeats**: a parameter is unique by the pair
  "name + location". Before, one and the same parameter passed by name and as a reference was
  printed twice, and the document became invalid.
- `Paths`, `PathItems` and `Responses` take the extensions as the first parameter of the constructor,
  so their items are passed by name only (as before) or through `fromArray()`.

### Added

- **`Paths\DeferredOperation`** — a deferred reference to an operation. Without it an operation that
  refers to itself (pagination) could not be described at all: an object cannot be passed to its own
  constructor. `Link::$operation` accepts it as well.
- **`Schemas\Null\Schema`** — the `null` type out of 3.1: a single value is admissible. 3.0 has no
  such type, and the build rejects it.
- **`format` on a schema of any type.** The specification declares it an optional annotation
  applicable to any value, so it moved into `AbstractSchema`. Strings, integers and numbers still
  accept an enum of the known values; the `$format` property is now a string — what will land in the
  document.
- **The `x-*` extensions** — the `Extensions` class and an `extensions` parameter on every object
  where the specification admits them. The name is written without the prefix; a name with the prefix
  and the reserved `oai-`/`oas-` are rejected. The Paths Object, an operation's Responses Object and
  the Callback Object take the extensions as the second argument of `fromArray()`; on `webhooks` and
  the sections of `components` they are rejected — there the extensions belong to the object itself.
  On a `Discriminator` extensions are admissible in 3.1 only.
- **Strict reading — `Reader::read($content, strict: true)`, and that is the default.** A field the
  specification does not define the package used to drop in silence: a document is read in order to
  be changed and written back, and such a field disappeared without a trace. Now reading names every
  place it did not understand (`strict: false` brings the old behaviour back). What was read and
  dropped — `uniqueItems` on a string, the siblings of a `$ref` in 3.0, `encoding` on JSON, an
  example beside an enumeration — does not count as misunderstood.
- The empty `toObject()` overrides on `Integer\EnumSchema` and `Number\EnumSchema` were removed: the
  parent already printed everything and appended the extensions.
- What used not to be checked now is: three files in a ring (`a → b → c → a`) and a reference outside
  the documents handed over, a URL included — the refusal says exactly "the file was not handed to
  the reader" rather than "the target was not found".
- **`SchemaReader` was cut** from 1421 lines into four parts along what is read by different rules:
  `ValueReader` for the values (`default`, `example`, the members of an enumeration: a value has no
  keywords but does have a type), `EnumReader` for the enumerations (the set of values is taken
  apart, and it decides the fate of the neighbouring keywords), `Keywords` for which type of value a
  keyword applies to (three of the passes need that knowledge), and `SchemaReader` itself for picking
  the branch by the type and reading that branch's keywords. The calls from outside did not change.
- **The words of the core vocabulary were gathered into `Schemas\Abstract\Resource`:** `$id`,
  `$schema`, `$vocabulary`, `$anchor`, `$dynamicAnchor`, `$dynamicRef`, `$defs` and `$comment` leave
  the schemas' constructors and are passed as one `resource:` object. Eight parameters became one,
  and `Array\Schema` had thirty-eight of them.

  The grouping follows the specification rather than "rarely used": this is exactly the 2020-12 core
  vocabulary — words about the schema itself rather than about the value. Hence the shared rules,
  which `Resource` now checks itself: a dialect and a vocabulary are admissible at the root of a
  resource only (beside an `$id`), and in 3.0 the whole group is absent. The annotations (`title`,
  `description`) and the composition (`allOf`, `oneOf`) were not folded up: those are logical groups,
  but `description` and `allOf` are the most frequent fields in a document, and a wrapper around them
  would cost more than it saves.
- **`$schema` on a schema (3.1)** — the dialect of a single schema. `jsonSchemaDialect` sets it for
  the whole document, and the specification allows overriding it "in any Schema Object that is a
  schema resource root", that is, where an `$id` is declared; without an `$id` the package rejects
  it. Before there was nothing to write this with, and strict reading wrongly counted the keyword as
  unknown.
- **A failure during a build names the place in the document:**
  `openapi.json/paths/~1users~1{id}/get/responses/200/content/application~1json/schema: …`. Before,
  the message said what was missing but not where: what refuses is the deepest object, and it does
  not know its own place. Now the containers add it on the way up, as a pointer by RFC 6901, as the
  reader writes it. The place is available separately as well — `place()` on any exception of the
  package; on a failure inside a constructor it is `null`.
- **Verbose printing — `prepareToSave(verbose: true)`, `encode(verbose: true)`,
  `save(..., verbose: true)`.** The values that equal their default are written out. The document
  does not change because of it, and that is checked: the verbose form, read back and built briefly,
  has to match the brief one. Where a default changes the meaning (`encoding` in 3.1, the exclusive
  bounds, `nullable` in 3.1), the verbose form stays silent.
- **`declareType: false` on the string, number and integer schemas** — as on the object and the
  array. `{"minLength": 3}` declares no type: a value of another type fits such a schema, and before
  there was nothing to write that with (see README, "A check without a declared type").
- `Untyped\EnumSchema` — an enumeration of values of different types.
- `HttpSecurityScheme` — any IANA HTTP scheme except basic and bearer (Digest, Negotiate…).
- `Securities\Role` — roles for schemes of other types (3.1; in 3.0 their list has to be empty) and
  `Securities\ScopeName` — a scope named by its name, for when the document does not declare it.
- The checks on enumerations: an empty list, repeats, a `null` among the values, a `default` outside
  the list, values outside a known `format`, values of one type in an `Untyped\EnumSchema`.
- Reading enumerations by their meaning: see README, "Enumerations".
- `Object\Schema(required: …)` — the required names that `properties` does not describe (the "one of
  the fields is required" pattern, through `oneOf`).
- `RealWorldTest` on the OAI examples and the Swagger Petstore, `tests/Support/SemanticDiff` with the
  list of insignificant differences, and the `composer real-world` command — seventeen documents,
  among them one from each of several generators on purpose: FastAPI, NestJS, drf-spectacular,
  go-swagger, kube-openapi, .NET, Go, Spring Boot, openapi-extractor for PHP and the Elasticsearch
  specification compiler. Two of the documents the specification does not admit, and the refusal is
  checked together with its wording.
- **`composer validate-output`** — a third-party check of the output. The read → write round trip
  proves the document was not lost, but not that it is valid: the package could read and write in the
  same wrong way. The command builds every `BuilderTest` case in JSON and in YAML (the YAML encoder
  is our own, and its mistakes are invisible in JSON) and hands Redocly's structural rules the
  result. Where the validator itself departs from the specification is listed in `KNOWN_DEFECTS`,
  with an explanation of every entry.
- The `kitchen-sink-30` and `kitchen-sink-31` cases — one document per version, holding every object
  of the specification.
- **`RoundTripPropertyTest`** — the same round trips on documents nobody wrote. `RandomDocument`
  combines the objects of the package at random (one file and several, both versions, JSON and YAML,
  brief and verbose) and the properties are stated as laws: building is idempotent, so what is read
  from a built document and built again has to come out the same text. The generator carries its own
  xorshift rather than `mt_rand`, because a failure that cannot be repeated by its seed is not a
  failure anybody can fix. The two defects above were found by it within the first two hundred
  documents.
- `.gitattributes`: the tests, the corpus of real specifications and the development tooling are
  `export-ignore`d, so `composer require --prefer-dist` no longer unpacks them into `vendor/`; the
  line endings in the repository are normalised to `\n`, because the fixtures' expected JSON must not
  become CRLF on a Windows checkout.
- CI runs PHP 8.5 as a job that does not gate the build: `composer.json` allows `^8.3`, so the
  runtime has to be tried somewhere, while a deprecation raised inside php-cs-fixer or PHPStan on a
  runtime newer than they support is not a defect of this package.
- `composer coverage` and a CI job for it: the point is not a number to hit but the lines no test has
  ever executed. The test jobs stay without a coverage driver — the suite is run far more often than
  it is read.

### Fixed

- **A Link could not name an operation outside `paths`.** An operation in `webhooks`,
  `components.pathItems` or a Callback Object is an operation of the document all the same —
  `operationId` "MUST be resolved within the scope of the OpenAPI Description" — but the reader
  declared the ids of `paths` alone, so it refused a document the package itself had just written.
  Both sides now know all four places a Path Item Object lives in, and `operationRef` points into
  any of them. A Link that names an operation by `operationId` is checked too: the written document
  says nothing about where that operation is, so a dangling id is now a build error rather than a
  link somebody discovers later.
- **A `$dynamicRef` to a neighbouring file wrote an anchor that was not there.** A dynamic anchor is
  found by name, and a plain `#node` names one in the file that carries it, so a target in another
  document was unreachable — the build wrote it and the reader would not read it. The reference now
  carries the file (`other.yaml#node`), and the reader resolves it there. A target that no document
  declares in `components.schemas` has no name at all, and the build says so instead of writing a
  reference that leads nowhere.
- **A reference to `components.requestBodies` was spread out as a copy** instead of a `$ref`: an
  operation wrote the body in full (236 places in Elasticsearch).
- **A `$ref` to `components.callbacks` was read as a callback expression**, and the output was
  `"$ref": {}` — an inadmissible form. The section itself was not declared in the registry at all,
  so such a reference resolved nowhere.
- **The checks on strings and numbers were lost on a schema without a `type`** (`minLength`,
  `pattern`, `multipleOf`, the bounds), and in 3.0 a schema with a keyword about arrays
  (`uniqueItems`, `minItems`) rejected the document outright: the "items is required" rule belongs to
  a declared `type: "array"` rather than to any mention of an array.
- **A reference's own description (3.1) was lost** on a request body and on an operation's parameter.
- **A parameter with `content` was written invalidly:** the content type was printed as a `mimeType`
  field beside `content` rather than as a key inside it. Found by the third-party validator
  (`composer validate-output`) — the read → write round trip let it through, because the reader read
  the package's own mistake back.
- **The YAML encoder spoiled empty maps and lists inside a sequence:** `-       servers:` instead of
  `- {}`. The document did not read back after that.
- **A cookie parameter lost its `explode`:** the reader put `false` in, while the class defaulted to
  `true` (the form style), so the value changed on reading. Now the reader passes `null`, and every
  parameter class knows the default for its own style.
- **Large integer bounds were spoiled.** `maximum: 9223372036854775807` went through a float and
  became `-9223372036854775808`, and `18014398509481983` came out one greater. Numbers are read and
  kept as they were written: the bounds, `multipleOf` and the number values take both an integer and
  a fraction.
- **`default` was lost on a schema without a type** (FastAPI writes `{"$ref": …, "default": …}`).
- **`example: {}` on an object made a YAML document be rejected.** Through ext-yaml an empty map
  arrives as an empty array, and an object value did not accept it; the schema knows the position, so
  a map stays a map (the Camunda 8 document).
- **An empty `enum` made a document be rejected.** JSON Schema admits it: nothing matches such a
  list, and it is read as an equivalent `not: {}`.
- **A scope declared in no flow made a document be rejected.** Of a Security Requirement the
  specification asks only that the scheme's name be declared in `components.securitySchemes`; it does
  not require the scopes to be declared. Now such a scope is read as a `Securities\ScopeName`, and the
  message about an undeclared scheme became precise.
- **A node's path in the reader was not a JSON Pointer**: the template `/users` was not escaped, so
  the pointer `#/paths/~1users/get` never matched a node. The operations under `paths` were built
  twice, an `operationRef` to them did not resolve, and an operation referring to itself was rejected
  as "a cycle that only schemas may form".
- **A `type` of several types (3.1) made a document be rejected**, and `type: "null"` quietly lost
  the type. A union is read as an equivalent `anyOf` of the schemas of those types: the keywords that
  apply to one of them go into its branch.
- **`format` was lost on objects and arrays** (33 cases in Twilio: `uri-map`,
  `phone-number-capabilities`).
- **The `x-*` extensions were lost on reading.** There were 2670 of them in Stripe, 1353 in GitHub,
  442 in Asana and 226 in Twilio; those documents now survive the round trip without a loss.
- **`scheme: Bearer` was read as basic** and lost its `bearerFormat`: an HTTP scheme's name is
  case-insensitive.
- **An operation's `security: []` was lost**, and an open operation became a closed one.
- **A `{}` requirement was lost**, and optional authorisation became mandatory.
- Roles in a Security Requirement (3.1) and openIdConnect scopes were rejected on reading.
- A numeric enumeration printed `"type": "double"`.
- A nullable enumeration held no `null`, and by 3.0.3 null was inadmissible.
- The enumerations lost `readOnly`, `format`, `xml` and the other annotations on reading; an `enum`
  with a `null` or one of booleans was not read at all.
- A 3.1 document with `webhooks` alone got a superfluous `paths: {}`.
- An empty Path Item with `{variables}` in its template was rejected, although the specification
  allows it.
- `ReadmeTest` failed without ext-yaml instead of being skipped.
- **Names such as `7` and `-1` were lost or broke the parsing** (GitHub's `+1`/`-1` reactions).
- **ext-yaml spoiled the data:** `521621,621373` and `12:30` became numbers, `no`, `on` and `y`
  became booleans, in keys as well, and `012` was read as 10.
- **An Encoding without an `explode` was read as `explode: false`**, although for `form` the default
  is `true`.
- **An alias of a component** (`A: {$ref: B}`) merged with `B`, and the references wandered off to
  another name.
- The siblings of a `$ref` in 3.1 were lost.
- A `null` in `example`, `default` and `value` was lost.
- A `required` with names outside `properties` was lost.
- An `example` on a schema without a `type` was not read; an `example` of the wrong type made the
  whole document be rejected.
- A self-reference in `discriminator.mapping` did not build.
- A response component named `500` was written as `x500`.
- A nameless unregistered parameter gave `"name": 0`.

## [2.1.0]

### Added

- **Reading finished specifications** — `Reader::read()` and `Reader::readAll()`. The parsing returns
  the very objects the `$ref`s pointed at, so writing it back gives the original document byte for
  byte. Cross-file references and recursive schemas work.
- `Serialization\DecoderInterface`, `JsonDecoder`, `YamlDecoder` (over ext-yaml) and `Structure` for
  adapters of your own.
- `Components\Schemas\Abstract\DeferredSchema` — a deferred reference to a schema. Without it a
  recursive schema could neither be read nor built: an object cannot be passed to its own
  constructor.
- `Components\Links\Link\Parameter::expression()` — an arbitrary runtime expression, which the
  specification allows and the named constructors did not cover.

### Verified

- Six real specifications from the OAI repository (`petstore`, `petstore-expanded`, `uspto`,
  `callback-example`, `link-example`, `api-with-examples`) were read, written back and **checked by
  the Redocly 2.53 validator — all six are valid**. The contents match the originals, apart from the
  explicitly written default values, which the package normalises.
- The whole `composer check` was run with the real tools: php-cs-fixer 3.95.25, PHPStan 2.2.14 and
  PHPUnit 10.5.64 — **127 tests, 167 assertions, green**.
- The tools' versions are pinned by `composer.lock` locally: the library does not publish it, so it
  is not in the repository.

### Fixed

- `Boolean\Value` accepted an `int`: a boolean default either failed with a `TypeError` or quietly
  became `1`. `Integer\Value` and `Number\Value` did not accept `null`.
- The default `explode` contradicted the specification: on a header parameter it was `true` (the
  simple style requires `false`), and on a cookie `false` (the form style requires `true`).
- The composition was narrowed by type: `String\Schema::$anyOf` required `String\Schemas`, and `$not`
  an `EnumSchema|self`. The specification does not require the members of an `anyOf` to match the
  type of the schema itself.
- `Object\Schema` required `properties` and `Array\Schema` `items`; both are optional (`items` is
  required in 3.0 only, which is now what is checked).
- An operation's `tags` were printed as Tag objects, although the specification asks for a list of
  names.
- A Header Object printed the `in` field, which the specification forbids in it.
- `required: false` was always printed, although that is the default value.
- **`format` was a closed enum**, although the specification declares it an open value:
  `format: uriref` out of `uspto.yaml` could not be built. Any string is accepted now.
- **A schema without a `type` but with `properties` lost them.** The specification does not require a
  `type`; `Object\Schema` and `Array\Schema` gained `declareType: false`.
- **A nested list inside an example value was spoiled**: `OpenapiObject::toNative()` recursed into
  itself only, and a nested container went into the JSON as a raw object.
- **A media type's `schema` was required**, although the specification does not require it.
- `get_object_vars()` was replaced with `Structure::vars()`: PHP casts a numeric property name to an
  int, and the array keys stopped being strings.

## [2.0.0]

A major release. In `1.0.0` parts of the specification were serialised wrongly or lost, so the fixes
inevitably break compatibility.

### Fixed

- **`components.securitySchemes` were built invalidly.** Only `type` and `description` were printed;
  `flows` on oauth2, `name` and `in` on apiKey, `scheme` on http and `openIdConnectUrl` on
  openIdConnect were lost. The oauth2 type was written as `oath2`, and http basic as `base`.
- **`$ref` leaked into `enum` and `default`.** The examples were looked for among the scalars, and
  strings have no identity, so any value that happened to equal an example turned into a reference —
  in positions where a `$ref` is inadmissible in principle.
- **A document could take somebody else's `components`.** In a multi-file build the check of the
  returned value compared a `bool` with `null`, because of which any document declared after the
  first got a `$ref` to the previous file's section and lost its own.
- **Fields were quietly lost**: `deprecated` on schemas and parameters, `examples` on parameters,
  `server` on a Link; `externalDocs` was printed without `toObject()` and leaked
  `"description": null`.
- `info.contact` was printed as `contacts` — an invalid field name.
- `Openapi::findScope()` and the security scheme lookup compared objects by value rather than by
  identity, and picked the first matching scheme instead of the right one.
- A parameter's schema did not turn into a `$ref`, although in a media type it did.
- `prepareToSave()` without a path gave a key with a leading slash.
- `Discriminator` was abstract, with no subclasses and no serialisation: polymorphism through `oneOf`
  did not work at all.
- An `enum` schema with a scalar `default` determined the type as `object`.

### Added

- **OpenAPI 3.1** beside 3.0: `Version`, `webhooks`, `jsonSchemaDialect`, `info.summary`,
  `license.identifier`, `components.pathItems` and the `mutualTLS` scheme. The differences between
  the dialects (`nullable` versus an array type, the exclusive bounds) are applied automatically.
- **The whole JSON Schema 2020-12 vocabulary**: `const`, `if`/`then`/`else`, `prefixItems`,
  `contains`, `patternProperties`, `propertyNames`, `dependentRequired`, `dependentSchemas`,
  `unevaluated*`, `content*`, `$defs`, `$id`, `$anchor`, `$dynamicRef`, `$vocabulary`. Addressing is
  by objects everywhere: a schema inside `$defs` is referred to just as an ordinary component is, and
  `$dynamicRef` takes a schema with a `$dynamicAnchor` rather than a string.
- **YAML output** with no external dependencies, through the pluggable
  `Serialization\EncoderInterface`.
- `Builder::save()` and `Builder::encode()`.
- **A Reference Object** with a `summary` and a `description` of its own (3.1).
- **The checks of the specification's invariants**: a path template against the path parameters, an
  operation without responses, inverted bounds, `multipleOf <= 0`, a `discriminator` without a
  composition, mutually exclusive fields. The non-negative bounds are declared as `int<0, max>` and
  are caught by PHPStan before anything runs.
- A single `OpenapiExceptionInterface` for every exception of the package.

### Broken

Renamings:

| Was | Became |
|---|---|
| `Components\SecuritySchemes\BaseHttpSecurityScheme` | `BasicHttpSecurityScheme` (without `bearerFormat`) |
| `Info\Contacts`, `Info::$contacts` | `Info\Contact`, `Info::$contact` |
| `Openapi::finsSecurity()` | `Openapi::findSecurity()` |
| `Components\Schemas\Object\Discriminator` | `Components\Schemas\Abstract\Discriminator` |
| `Components\Links\Link\Variable`, `Variables` | `Servers\Variable`, `Servers\Variables` |

Changed signatures:

- `Builder::__construct()` requires the file names as the array's keys and rejects positional
  arguments. `Builder::save()` takes an `EncoderInterface` instead of `json_encode` flags;
  `Builder::DEFAULT_JSON_FLAGS` moved to `JsonEncoder::DEFAULT_FLAGS`.
- `prepareToSave('')` no longer adds a leading slash to the file names.
- `Components::$examples` is now a `Components\Examples` of `Example` objects rather than a map of
  bare values.
- `Content` and the parameters that have a schema: `$examples` was split into `$example` (one value)
  and `$examples` (a map of `Example`), and they are mutually exclusive.
- `Callbacks` and `Openapi::$webhooks` take `PathItems` rather than `Paths`: there the key is a name
  or a runtime expression rather than a path template.
- `Servers\Server` no longer takes `enum` and `default` — those are fields of a Server Variable.
  `Variable::$default` is now a `string`, as the specification requires.
- `Parameters\Path\SchemaParameter` no longer takes `allowEmptyValue` and `allowReserved`: by the
  specification those are for query only.
- `Server::toObject()` and `Servers::toArray()` no longer take a `Process`.
- `Openapi::findExample()` takes an `Example`.

### Other

- `composer.json`: `type` was corrected from `project` to `library`.
- PHPStan at its maximum level with every additional option, php-cs-fixer on `@PhpCsFixer:risky` +
  `@PHP83Migration`, CI on PHP 8.3 and 8.4.
- `ReadmeTest` executes every PHP example from README.

### Known limitations

- By the specification a `Security Requirement Object` refers to the schemes of its own document, so
  the scopes are not looked up in the neighbouring files.
- The invariants of the specification are checked rather than the meaning of the document.

## [1.0.0]

The first release.
