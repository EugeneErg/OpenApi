# eugene-erg/open-api

Объектный конструктор спецификаций **OpenAPI 3.0 и 3.1** для PHP 8.3+.

Схемы, параметры, ответы и security-схемы описываются обычными PHP-объектами. Если один и тот же
объект используется в `components` и где-то ещё, при сборке он автоматически превращается в `$ref` —
вручную ссылки писать не нужно. Дедупликация работает и между несколькими файлами спецификации.

## Возможности

- **Типобезопасность.** Всё, что можно выразить типом или enum, выражено типом или enum.
- **Автоматические `$ref`.** Сравнение идёт по идентичности объекта, а не по значению: один и тот же
  `$userSchema` в десяти местах даст десять ссылок на одну схему.
- **Мультифайловые спецификации.** `components.yaml` + `paths.yaml` со ссылками вида
  `components.yaml#/components/schemas/User`.
- **Полные security-схемы**: `apiKey`, `http basic`, `http bearer`, `oauth2` (все четыре flow),
  `openIdConnect`.
- **Два диалекта.** 3.0 и 3.1 различаются записью `nullable` и исключающих границ — пакет
  выбирает нужную форму сам, объекты схем при этом одни и те же.
- **Именованные аргументы как ключи.** `new Schemas(User: $user)` вместо массивов со строками.
- **`format` у любого типа.** JSON Schema объявляет его необязательной аннотацией, поэтому
  `format: uri-map` у объекта сохраняется; у строк и чисел есть enum известных значений.
- **JSON и YAML** на выходе, без внешних зависимостей.
- **Расширения `x-*`** у всех объектов, где их допускает спецификация.
- **Чтение готовых спецификаций** обратно в объекты, со ссылками на те же экземпляры.
- **Невалидную спеку собрать нельзя**: часть ошибок ловит PHPStan, остальные — конструкторы.

## Установка

```bash
composer require eugene-erg/open-api
```

## Быстрый старт

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
                            // $user уже лежит в components, поэтому здесь будет $ref
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

// Ключ массива — имя файла. Именно оно попадает в кросс-файловые $ref.
$builder = new Builder(...['openapi.json' => $openapi]);

// Вариант 1: получить stdClass и распорядиться им самому
$documents = $builder->prepareToSave();           // ['openapi.json' => stdClass]

// Вариант 2: сразу записать на диск
$builder->save(__DIR__ . '/public/docs');         // public/docs/openapi.json

// Вариант 3: только сериализовать, без записи
$contents = $builder->encode();                   // ['openapi.json' => '{ ... }']
```

Для YAML имя файла задаётся тем же ключом, а формат — энкодером:

```php
(new Builder(...['openapi.yaml' => $openapi]))->save(__DIR__ . '/public/docs', new YamlEncoder());
```

> **Имя файла обязательно передавать ключом.** `new Builder($openapi)` даст числовой ключ `0`
> и, как следствие, невалидные ссылки вида `0#/components/schemas/User`. Конструктор такой вызов
> отклонит с `InvalidArgumentException`.

## Формат вывода

`Builder` умеет и JSON, и YAML. Обе реализации живут в пакете, внешних зависимостей нет:

```php
use EugeneErg\OpenApi\Serialization\JsonEncoder;
use EugeneErg\OpenApi\Serialization\YamlEncoder;

$builder->save('docs');                      // JSON по умолчанию
$builder->save('docs', new YamlEncoder());   // YAML
$builder->save('docs', new JsonEncoder(JSON_UNESCAPED_SLASHES)); // свои флаги
```

Имя файла берётся из ключа, переданного в `Builder`, — энкодер его не меняет,
так что расширение выбираете вы: `new Builder(...['openapi.yaml' => $openapi])`.
Если в проекте уже есть `symfony/yaml` и хочется использовать её, достаточно
реализовать `Serialization\EncoderInterface`.

Кавычки в YAML ставятся консервативно: лишняя кавычка допустима, потерянный тип — нет.
Код ответа `"200"` останется строкой, `3.0.3` — тоже, а описания на любом языке
печатаются без кавычек.

## Что проверяется при сборке

Часть ошибок ловится статически: неотрицательные границы объявлены как `int<0, max>`,
так что `minLength: -5` PHPStan не пропустит. Остальное проверяется в конструкторах:

- путь не начинается со слэша, либо `{id}` встречается в шаблоне дважды
- в шаблоне есть `{id}`, но нет соответствующего path-параметра — или наоборот
- операция без единого ответа (в 3.0 `responses` обязательны, в 3.1 их можно не указывать)
- противоречивые перечисления — см. «Перечисления»
- нижняя граница больше верхней, `multipleOf` не положительный
- `discriminator` без `oneOf` / `anyOf` / `allOf`
- одновременно заданные `value` и `externalValue`, `example` и `examples`

Того, что выражается типами, в API просто нет: у path-параметра отсутствуют
`allowEmptyValue` и `allowReserved` (они только для query), а `required` у него
всегда `true`.

## Перечисления

Закрытый набор значений описывается отдельной схемой — `EnumSchema` своего типа:

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

Почему не параметр `enum` у обычной схемы. Когда значения перечислены, любое проверяющее
ключевое слово (`minLength`, `pattern`, `minimum`, `items`, `properties`, `allOf`, `not`…)
либо выполняется для всех значений и ничего не меняет, либо отсекает часть из них — и тогда
это ошибка. Третьего не бывает. Поэтому у `EnumSchema` таких параметров нет: бесполезное
написать нельзя, полезного не теряется. Остались аннотации — `title`, `description`,
`readOnly`/`writeOnly`, `deprecated`, `xml`, `format`, `default` и идентификаторы 3.1.
`example` убран по той же причине: у перечисления он ничего не добавляет.

Что пакет делает сам:

- **`const`.** Одно значение в 3.1 печатается как `const`, в 3.0 — как `enum` из одного
  элемента. Отдельного параметра `const` нет: это то же перечисление.
- **`nullable`.** `null` добавляется и во флаг (тип), и в сам перечень: по 3.0.3 без этого
  null недопустим. Писать `null` в значения не нужно — это ошибка.
- **Проверки.** Отклоняются пустой перечень, повторы (`1` и `1.0` — одно значение, порядок
  ключей объекта не важен), `default` вне перечня, значения, не подходящие под известный
  `format` (`date`, `date-time`, `email`, `uuid`, `ipv4`, `ipv6`, `byte`, `int32`, `float`).
- **Тип.** Для значений одного типа есть только типизированная схема; `Untyped\EnumSchema`
  принимает лишь смешанные наборы. `Boolean\EnumSchema` принимает одно значение: перечень
  из `true` и `false` ничего не ограничивает.

Если перечисление всё же нужно сочетать с композицией, это эквивалентно `allOf`:

```php
$deletedDog = new Schemas\Untyped\Schema(allOf: new Schemas\Untyped\Schemas(
    $dog,
    new Schemas\Object\EnumSchema(new Schemas\Object\Objects(
        new Schemas\Object\OpenapiObject(deleted: true),
    )),
));
```

При чтении готового документа перечисление разбирается по смыслу. `example` и слова,
неприменимые к типу, отбрасываются. Простые проверки (`maxLength`, `pattern`, границы,
`required`…) применяются к каждому значению: если все проходят, слово отбрасывается,
если нет — документ отклоняется, потому что часть значений недостижима. Всё прочее
сохраняется без потерь через `allOf`. `nullable: true` без `null` в перечне читается
как nullable — так его и пишут на практике.

## Расширения

Поля `x-*` есть почти у каждого объекта спецификации. Префикс добавляется всегда,
поэтому имя пишется без него:

```php
use EugeneErg\OpenApi\Extensions;

new Schemas\String\Schema(extensions: new Extensions(internal: true));
```

Даёт `{"type": "string", "x-internal": true}`. Имя с дефисом именованным аргументом
не записать, поэтому оно передаётся распаковкой. Смешивать нельзя: PHP запрещает
распаковку после именованных аргументов, так что либо всё именованными аргументами,
либо всё распаковкой:

```php
new Extensions(...[
    'internal' => true,
    'code-samples' => new Schemas\Array\OpenapiArray('curl …'),
]);
```

- Правило одно и без исключений, поэтому у каждого поля документа ровно одна запись:
  `x-legacy` даёт `x-x-legacy`, которое спецификация разрешает (от имени она требует
  только того, чтобы оно начиналось с `x-`). Обратная сторона: префикс никогда не пишут
  руками — написанный, он удвоится.
- Префиксы `x-oai-` и `x-oas-` спецификация 3.1 оставила за OpenAPI Initiative. Пакет их
  принимает — документ с ними законен, — но своё расширение так называть не стоит.
- Значение — любое значение JSON; объекты и списки задаются теми же
  `OpenapiObject` и `OpenapiArray`, что и примеры.
- Имена вида `7` передаются через `Extensions::fromArray()`, как и в остальных картах.

Расширения есть и у трёх объектов, которые выглядят как обычные карты: Paths Object,
Responses Object операции и Callback Object. Им расширения передаются вторым
аргументом `fromArray()`:

```php
Paths::fromArray(
    ['/users' => new Paths\Path(get: $onUserCreated)],
    new Extensions(section: 'users'),
);
```

А вот `webhooks` и разделы `components` спецификация объявляет как `Map[string, …]`:
расширения там принадлежат не карте, а самому объекту (`Openapi` и `Components`),
и попытка задать их отклоняется. Имя `x-…` в такой карте — имя компонента, а не
расширение: в `components.headers` у GitHub так назван заголовок
`x-common-marker-version`, и он читается именно как компонент.

## Коды ответов

Именованный аргумент в PHP не может начинаться с цифры, поэтому HTTP-коды пишутся с префиксом `x`,
который снимается при сборке:

```php
new Responses(
    x200: new Responses\Response(description: 'OK'),
    x404: new Responses\Response(description: 'Not found'),
    x4XX: new Responses\Response(description: 'Client error'),  // wildcard тоже работает
    default: $generalError,
);
```

У операции ключ ответа обязан быть кодом или `default`: `new Responses(ok: …)` не соберётся.

## Имена: '7', '-1' и fromArray()

Карты (`Properties`, `Examples`, `Contents`, `Schemas` и остальные) принимают имена именованными
аргументами или распаковкой массива. У распаковки одна ловушка PHP: ключ `'7'` или `'-1'`
хранится как целое число и превращается в позиционный аргумент — имя пропало бы молча.
Поэтому элемент карты без имени — ошибка, а для любых имён есть `fromArray()`:

```php
$reactions = Schemas\Object\Properties::fromArray([
    '+1' => new Schemas\Object\Property(new Schemas\Integer\Schema()),
    '-1' => new Schemas\Object\Property(new Schemas\Integer\Schema()),
]);
$example = Schemas\Object\OpenapiObject::fromArray(['0' => 'first', '1' => 'second']);
```

Списки (`Strings`, `Tags`, `Servers`, `allOf`…) наоборот отклоняют имена — при записи они
пропали бы. `Schemas` служит и тем и другим: `components.schemas`, `mapping` и `$defs` требуют
имён, `allOf`, `anyOf`, `oneOf` и `prefixItems` — их отсутствия.

Единственное место, где элемент карты можно передать без имени, — параметры операции:
параметр, зарегистрированный в `components.parameters`, берёт имя из регистрации и
выводится как `$ref`. Незарегистрированный безымянный параметр сборка отклонит.

## Обязательные поля вне properties

Описанное свойство делается обязательным флагом `Property(required: true)`. Имена, которые
в `properties` не описаны — их форму задают `additionalProperties`, `patternProperties`
или композиция, — перечисляются в `required` самой схемы:

```php
// «нужно ровно одно из двух полей»
$target = new Schemas\Untyped\Schema(oneOf: new Schemas\Untyped\Schemas(
    new Schemas\Object\Schema(declareType: false, required: new Strings('id')),
    new Schemas\Object\Schema(declareType: false, required: new Strings('owner', 'repo')),
));
```

Имя из `properties` здесь отклоняется (для него есть флаг), как и имя, которое при
`additionalProperties: false` не подходит ни под один `patternProperties`: такой объект
невозможен.

## Несколько файлов

Возвращайте из сборки несколько документов — ссылки между ними расставятся сами:

```php
$components = new Openapi(info: $info, components: new Components(schemas: $schemas));
$paths = new Openapi(info: $info, paths: $paths);

(new Builder(...[
    'components.yaml' => $components,
    'paths.yaml' => $paths,
]))->prepareToSave();
```

В `paths.yaml` ссылки получат вид `components.yaml#/components/schemas/User`.

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
            // имена скоупов с двоеточием нельзя передать именованным аргументом
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
    // объект Scope сам найдёт свою схему и своё имя
    security: new Securities(new Securities\SecuritySchemes($readPets)),
);
```

Даёт `security: [{ "petstoreAuth": ["read:pets"] }]`.

Остальные схемы:

```php
new SecuritySchemes\ApiKeySecurity\Scheme(name: 'X-Api-Key', in: SecuritySchemes\ApiKeySecurity\In::Header);
new SecuritySchemes\BasicHttpSecurityScheme();
new SecuritySchemes\BearerHttpSecurityScheme(format: 'JWT');
new SecuritySchemes\HttpSecurityScheme('Digest');   // любая HTTP-схема IANA, кроме basic и bearer
new SecuritySchemes\OpenIdConnectSecurityScheme(openIdConnectUrl: 'https://example.com/.well-known/openid-configuration');
```

Скоупы oauth2 объявлены в самой схеме, поэтому передаётся объект `Scope`: его имя и схема
берутся из объявления и разойтись не могут. Объявлять скоуп спецификация не требует —
у openIdConnect список публикует провайдер, а oauth2-документ может требовать скоуп,
которого нет ни в одном flow. Такой скоуп называется именем: `Securities\ScopeName`.
Остальные схемы в 3.1 могут требовать роли — `Securities\Role`; в 3.0 у них список
обязан быть пуст, и сборка это проверит.

```php
$bearer = new SecuritySchemes\BearerHttpSecurityScheme();
$oidc = new SecuritySchemes\OpenIdConnectSecurityScheme('https://example.com/.well-known/openid-configuration');

new Securities(
    new Securities\SecuritySchemes(new Securities\Role($bearer, 'admin')),     // только 3.1
    new Securities\SecuritySchemes(new Securities\ScopeName($oidc, 'openid')),
    new Securities\SecuritySchemes(),   // {} — разрешён анонимный доступ
);
```

У операции `security: null` (по умолчанию) означает «как у документа», а `new Securities()` —
«операция открыта», даже если документ требует авторизацию.

## Версия документа

По умолчанию собирается `3.0.3`. Версия задаётся через enum `Version`:

```php
use EugeneErg\OpenApi\Version;

new Openapi(info: $info, version: Version::V310);
```

Одни и те же объекты схем дадут разный вывод:

| | 3.0 | 3.1 |
|---|---|---|
| `new Schemas\String\Schema(nullable: true)` | `{"type": "string", "nullable": true}` | `{"type": ["string", "null"]}` |
| `new Schemas\Number\Schema(minimum: 0, exclusiveMinimum: true)` | `{"minimum": 0, "exclusiveMinimum": true}` | `{"exclusiveMinimum": 0}` |
| `new Schemas\String\EnumSchema(new Schemas\String\Strings('a'))` | `{"type": "string", "enum": ["a"]}` | `{"type": "string", "const": "a"}` |

`webhooks` существуют только в 3.1 — при попытке задать их для 3.0 конструктор
бросит `InvalidArgumentException`:

```php
new Openapi(
    info: $info,
    version: Version::V310,
    webhooks: new PathItems(...[
        'userCreated' => new Paths\Path(post: $onUserCreated),
    ]),
);
```

## Серверы

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

## Полиморфизм

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

Схемы из `mapping` должны быть зарегистрированы в `components.schemas`, иначе сборка упадёт с
`ComponentsNotFoundOpenapiException`.

## Исключения

Все исключения пакета реализуют `EugeneErg\OpenApi\Exceptions\OpenapiExceptionInterface`:

| Исключение | Когда |
|---|---|
| `ScopeNotFoundOpenapiException` | `Scope` не объявлен ни в одном oauth2-flow документа |
| `SecuritySchemeNotFoundOpenapiException` | схема не зарегистрирована в `components.securitySchemes` |
| `OperationNotFoundOpenapiException` | `Link` ссылается на операцию, которой нет в `paths` |
| `ComponentsNotFoundOpenapiException` | требуется `$ref`, но объект не лежит ни в одном `components` |
| `InvalidPathOpenapiException` | шаблон пути не согласован с path-параметрами |
| `InvalidSchemaOpenapiException` | границы или ключевые слова схемы несогласованы |
| `InvalidArgumentOpenapiException` | остальные нарушения инвариантов спецификации |

## Чтение готовой спецификации

Обратная задача: разобрать существующий документ в объекты, поправить и записать обратно.

```php
use EugeneErg\OpenApi\Reader;
use EugeneErg\OpenApi\Serialization\YamlDecoder;

$openapi = Reader::read(file_get_contents('openapi.json'));

// несколько файлов — ключами, как и в Builder: ими разрешаются кросс-файловые ссылки
$documents = Reader::readAll([
    'components.yaml' => file_get_contents('components.yaml'),
    'paths.yaml' => file_get_contents('paths.yaml'),
], new YamlDecoder());
```

Главное свойство: **одна и та же ссылка даёт один и тот же объект**. Схема, на которую
`$ref` указывает из трёх мест, будет одним экземпляром — и при обратной записи снова
станет тремя ссылками, а не тремя копиями. Рекурсивные схемы восстанавливаются настоящим
циклом через `DeferredSchema`.

Документ читается по смыслу, а не дословно. То, что ни на что не влияет, отбрасывается,
эквивалентные формы приводятся к одной:

- значения, равные умолчанию, и пустые списки и карты;
- `example`, не подходящий под тип своей схемы;
- `encoding` у содержимого, отличного от форм и multipart;
- соседи `$ref` в 3.0 (там они не действуют). В 3.1 `$ref` — обычное ключевое слово,
  и `{$ref, description}` читается как `{allOf: [$ref], description}`; схема из одного
  `allOf` при записи снова становится самим элементом;
- особенности перечислений — см. «Перечисления».

Компонент, который лишь ссылается на другой (`UserBase: {$ref: UserCompact}`), остаётся
отдельным объектом: ссылки на `UserBase` не уедут на `UserCompact`. `null` в `example`,
`default` и `value` сохраняется — это значение, а не его отсутствие.

Противоречивый документ отклоняется с указанием места: например, требование
oauth2-скоупа, не объявленного ни в одном flow схемы.

Разбор JSON встроен. Для YAML нужен `ext-yaml`, либо свой десяток строк поверх
`DecoderInterface`. `YamlDecoder` читает по базовой схеме YAML 1.2, как требует OpenAPI:
ext-yaml по умолчанию следует YAML 1.1 и превращает `no` и `on` в логические значения,
`12:30` и `1,5` — в числа. Ограничение: `1e3` и `0o17` ext-yaml отдаёт строкой ещё до
разбора, поэтому в числовых ключевых словах их лучше не использовать.

Свой декодер поверх другого парсера:

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

## Как это устроено

`Builder` хранит документы и знает имена их файлов. Всю работу по ссылкам делает `Process` —
контекст сборки одного документа. Каждый `toObject()` получает `Process` и спрашивает у него:
«этот объект уже лежит в чьём-нибудь `components`?». Если да, на его месте окажется `$ref` —
локальный или с именем соседнего файла; если нет, объект развернётся по месту.
Через `Process` же доступна версия документа, поэтому схемы умеют печататься в обоих диалектах.

## JSON Schema 2020-12

В 3.1 схема — это полноценная JSON Schema. Поддержан весь словарь: `const`, `if`/`then`/`else`,
`prefixItems`, `contains`, `patternProperties`, `propertyNames`, `dependentRequired`,
`dependentSchemas`, `unevaluated*`, `content*`, `$defs`, `$id`, `$anchor`, `$vocabulary`.

Адресация остаётся по объектам — сырые строки ссылок писать не нужно нигде. Схема, лежащая
в `$defs`, ссылается так же, как обычный компонент:

```php
$street = new Schemas\String\Schema(minLength: 1);

$user = new Schemas\Object\Schema(
    properties: new Schemas\Object\Properties(
        road: new Schemas\Object\Property(schema: $street),
    ),
    defs: new Schemas\Untyped\Schemas(Street: $street),
);
// road => {"$ref": "#/components/schemas/User/$defs/Street"}
```

`$dynamicRef` тоже принимает объект, а не строку: сборщик сам возьмёт его `$dynamicAnchor`.

```php
$node = new Schemas\Object\Schema(dynamicAnchor: 'node');
new Schemas\Untyped\Schema(dynamicRef: $node);   // => {"$dynamicRef": "#node"}
```

Все эти слова допустимы только в 3.1 — при сборке 3.0 будет исключение с именем ключевого слова.

## Несколько типов и тип null

В 3.1 `type` может быть массивом. `null` в нём — это `nullable`, он читается как раньше:

```php
new Schemas\String\Schema(nullable: true);   // 3.1: {"type": ["string", "null"]}
```

Схема, у которой других типов нет вовсе, — это `{"type": "null"}`: допустимо
единственное значение. Для неё есть своя схема, и в 3.0, где такого типа нет,
сборка её отклонит:

```php
new Schemas\Null\Schema(description: 'Всегда null.');
```

Объединение нескольких типов пакет описывает по типу каждого варианта, поэтому
`{"type": ["string", "integer"]}` — это `anyOf` из двух схем:

```php
new Schemas\Untyped\Schema(anyOf: new Schemas\Untyped\Schemas(
    new Schemas\String\Schema(maxLength: 5),
    new Schemas\Integer\Schema(minimum: 0),
));
```

Читая компактную запись, пакет раскладывает её так же: слова, применимые к одному
из перечисленных типов, уходят в его ветку, общие остаются снаружи через `allOf`.

### Проверка без объявленного типа

`{"minLength": 3}` — это не «строка длиннее двух»: значение любого другого типа такой
схеме подходит, потому что проверка строки к нему не применяется. Поэтому проверку
можно написать, не объявляя тип, — `declareType: false` у схемы нужного типа:

```php
new Schemas\String\Schema(minLength: 3, declareType: false);   // {"minLength": 3}
new Schemas\Object\Schema(properties: $properties, declareType: false);
```

Обратное следствие: слово о значениях другого типа при объявленном типе не значит
ничего — `uniqueItems` у строки выполнено всегда, потому что строка не массив. Такое
слово пакет не печатает (см. «Что проверяется при сборке», третий принцип), а при
чтении оно отбрасывается: kube-openapi пишет `uniqueItems: true` у каждого
query-параметра, и в документе Kubernetes таких мест 550.

## Ссылки на операции и пагинация

`Link` указывает на операцию объектом, а не именем: на несуществующую так не сошлёшься.
Но операция, которая ссылается на саму себя, — «следующая страница» — в собственный
конструктор не передаётся, поэтому ссылка откладывается замыканием, как и у рекурсивных
схем:

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

Операция с `operationId` выводится по нему, без него — указателем на её место
в `paths` (`operationRef: "#/paths/~1users/get"`). Если целевой операции нет ни в одном
переданном `Builder` документе, сборка это отклонит.

## Ссылки с описанием

Обычно ссылку делать не нужно: передайте объект, и сборщик сам поставит `$ref`.
Reference Object нужен только там, где ссылка должна переопределить описание компонента
(в 3.1 у неё есть свои `summary` и `description`):

```php
use EugeneErg\OpenApi\Reference;

new Responses(
    x404: new Reference($notFound, description: 'Пользователь не найден.'),
    x500: $notFound,   // без переопределения — просто объект
);
```

Даёт `{"$ref": "#/components/responses/NotFound", "description": "..."}` и голый `$ref` соответственно.
Цель указывается тем же объектом, так что способ сослаться в пакете по-прежнему один.

Так же ссылаются тело запроса, параметр и обратный вызов:

```php
new Paths\Operation(
    responses: $responses,
    requestBody: new Reference($upload, description: 'Пользователь для создания.'),
    callbacks: new Components\Callbacks(onCreated: $onEvent),
    parameters: new Parameters\Parameters(
        // имя параметра берётся из объявления компонента, поэтому здесь его не передают
        queries: new Parameters\Query\Queries(new Reference($pageQuery, description: 'Своё описание.')),
    ),
);
```

Список параметров операции проверяется на повторы: спецификация считает параметр
уникальным по паре «имя + расположение», и второй `page` в query отклоняется —
даже если первый передан по имени, а второй ссылкой.

## Ограничения

- `Security Requirement Object` по спецификации ссылается на схемы того же документа, поэтому
  скоупы не ищутся в соседних файлах. Это ограничение самой спецификации, не пакета.
- Проверяются инварианты спецификации, а не смысл документа: пакет поймает `{id}` без параметра
  или перевёрнутые границы, но не скажет, что схема описывает не то, что отдаёт сервер.

## Разработка

```bash
composer install
composer check     # php-cs-fixer + phpstan (максимальный уровень) + phpunit
```

CI прогоняет то же самое на PHP 8.3 и 8.4.

Тесты устроены как пары файлов: `tests/Cases/<Test>/Objects/<name>.php` возвращает объекты,
`tests/Cases/<Test>/Jsons/<name>.json` — ожидаемый результат. Новые кейсы подхватываются
автоматически, регистрировать их в тесте не нужно.

Отдельно `ReadmeTest` исполняет каждый PHP-пример из этого файла, так что документация
не может разойтись с кодом незаметно.

`RealWorldTest` прогоняет полный круг «чтение → запись» на настоящих спецификациях
(`tests/Cases/RealWorldTest`, источники — в `SOURCES.md`) и сравнивает смысл, а не текст.
Всё, что считается незначимым различием, перечислено в одном месте —
`tests/Support/SemanticDiff.php`.

Крупные публичные спецификации слишком велики для репозитория, их проверяет отдельная команда:

```bash
composer real-world             # пятнадцать документов
composer real-world -- discord  # выборочно
```

Кроме крупных публичных API (GitHub в 3.0 и 3.1, Stripe, Twilio, Asana, Discord)
там нарочно собраны документы разных генераторов: FastAPI (Airflow), NestJS (Immich),
drf-spectacular (authentik), go-swagger (Ory Kratos), kube-openapi (Kubernetes),
.NET (Sonarr), Go (Hyperledger FireFly), компилятор спецификации Elasticsearch и пример
Redocly. У каждого свои привычки, и почти каждый принёс по ошибке.

Два документа из этого списка спецификация не допускает, и пакет их отклоняет:
у Sonarr путевой параметр, которого нет в шаблоне пути, у Kratos — требование
безопасности с необъявленной схемой. Прочитать их нельзя (объекты пакета такого
не выражают), а молча починить значит соврать, поэтому проверяется сам отказ
и его формулировка: она достанется тому, кто такой документ принесёт.

Отчёт отдельно считает места, где пакет выбрал равнозначную запись вместо исходной
(объединение типов — `anyOf`, перечисление рядом с применителем — `allOf`). Повторять
эту раскладку в проверке нельзя — она сверяла бы код сам с собой, — поэтому там
сверяется независимое условие: ни одно значение не потерялось.

Круг «чтение → запись» доказывает, что документ не потерялся, но не то, что он валиден:
пакет мог бы одинаково неверно и читать, и писать. Именно так и жил параметр с `content` —
тип содержимого печатался полем `mimeType` вместо ключа внутри `content`, и ридер читал
эту ошибку обратно. Поэтому вывод проверяется сторонним валидатором:

```bash
composer validate-output        # нужен Node.js; Redocly скачивается через npx
```

Команда собирает каждый кейс `BuilderTest` в JSON **и** в YAML — энкодер YAML свой,
его ошибки в JSON не видны (так нашлись пустые карты внутри последовательности) — и
прогоняет структурные правила Redocly. Среди кейсов есть `kitchen-sink-30` и
`kitchen-sink-31`: по одному документу на версию, где встречается каждый объект
спецификации.

Места, где расходится сам валидатор, перечислены в `KNOWN_DEFECTS` внутри
`tools/validate-output.php` — каждое проверено по тексту спецификации:

- Redocly ждёт от `$vocabulary` строку, а JSON Schema 2020-12 объявляет его объектом
  «URI => bool».
- Spectral (`oas3-schema`) несёт схему OAS 3.1.0, в которой нет `$ref` у Path Item
  (в тексте 3.1.1 он есть) и в Link Object опечатка `body` вместо `server`.

Проверка, падающая на чужих ошибках, хуже отсутствующей, поэтому такой список ведётся
явно и с объяснением, а не глушится целиком.

## Лицензия

MIT. См. [LICENSE](LICENSE).
