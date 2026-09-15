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
- **JSON и YAML** на выходе, без внешних зависимостей.
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
- операция без единого ответа
- нижняя граница больше верхней, `multipleOf` не положительный
- `discriminator` без `oneOf` / `anyOf` / `allOf`
- одновременно заданные `value` и `externalValue`, `example` и `examples`

Того, что выражается типами, в API просто нет: у path-параметра отсутствуют
`allowEmptyValue` и `allowReserved` (они только для query), а `required` у него
всегда `true`.

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
new SecuritySchemes\OpenIdConnectSecurityScheme(openIdConnectUrl: 'https://example.com/.well-known/openid-configuration');
```

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

Разбор JSON встроен. Для YAML нужен `ext-yaml`, либо свой десяток строк поверх
`DecoderInterface`:

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

## Лицензия

MIT. См. [LICENSE](LICENSE).
