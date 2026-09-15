# Changelog

Формат основан на [Keep a Changelog](https://keepachangelog.com/ru/1.1.0/),
версии — по [semver](https://semver.org/lang/ru/).

## [2.1.0]

### Добавлено

- **Чтение готовых спецификаций** — `Reader::read()` и `Reader::readAll()`. Разбор возвращает
  те же объекты, на которые указывали `$ref`, поэтому обратная запись даёт исходный документ
  байт в байт. Работают кросс-файловые ссылки и рекурсивные схемы.
- `Serialization\DecoderInterface`, `JsonDecoder`, `YamlDecoder` (поверх ext-yaml)
  и `Structure` для своих адаптеров.
- `Components\Schemas\Abstract\DeferredSchema` — отложенная ссылка на схему. Без неё
  рекурсивную схему нельзя было ни прочитать, ни собрать: объект не передать
  в собственный конструктор.
- `Components\Links\Link\Parameter::expression()` — произвольное runtime-выражение,
  которое спецификация разрешает, а именованные конструкторы не покрывали.

### Проверено

- Набор впервые прогнан настоящим PHPUnit: **127 тестов, 167 утверждений, зелёный**.

### Исправлено

- `Boolean\Value` принимал `int`: логическое значение по умолчанию либо падало
  с `TypeError`, либо молча превращалось в `1`. `Integer\Value` и `Number\Value`
  не принимали `null`.
- `explode` по умолчанию противоречил спецификации: у header-параметра было `true`
  (стиль simple требует `false`), у cookie — `false` (стиль form требует `true`).
- Композиция была сужена по типу: `String\Schema::$anyOf` требовал `String\Schemas`,
  `$not` — `EnumSchema|self`. Спецификация не требует, чтобы члены `anyOf` совпадали
  по типу с самой схемой.
- `Object\Schema` требовал `properties`, `Array\Schema` — `items`; оба необязательны
  (`items` обязателен только в 3.0, что теперь и проверяется).
- `tags` операции выводились объектами Tag, хотя спецификация требует список имён.
- Header Object выводил поле `in`, которое спецификация в нём запрещает.
- `required: false` выводился всегда, хотя это значение по умолчанию.

## [2.0.0]

Мажорный выпуск. В `1.0.0` часть спецификации сериализовалась неверно или терялась,
поэтому исправления неизбежно ломают совместимость.

### Исправлено

- **`components.securitySchemes` собирались невалидно.** Выводились только `type` и
  `description`; терялись `flows` у oauth2, `name` и `in` у apiKey, `scheme` у http,
  `openIdConnectUrl` у openIdConnect. Тип oauth2 писался как `oath2`, http basic — как `base`.
- **`$ref` протекал в `enum` и `default`.** Примеры искались среди скаляров, а у строк нет
  идентичности, поэтому любое значение, совпавшее с примером, превращалось в ссылку —
  в позициях, где `$ref` недопустим в принципе.
- **Документ мог забрать чужие `components`.** В мультифайловой сборке проверка возвращаемого
  значения сравнивала `bool` с `null`, из-за чего любой документ, объявленный не первым,
  получал `$ref` на секцию предыдущего файла и терял собственную.
- **Молча терялись поля**: `deprecated` у схем и параметров, `examples` у параметров,
  `server` у Link, `externalDocs` выводился без `toObject()` и протекал `"description": null`.
- `info.contact` выводился как `contacts` — невалидное имя поля.
- `Openapi::findScope()` и поиск security-схемы сравнивали объекты по значению, а не по
  идентичности, и выбирали первую подходящую схему вместо нужной.
- Схема параметра не превращалась в `$ref`, хотя в media type превращалась.
- `prepareToSave()` без пути давал ключ с ведущим слэшем.
- `Discriminator` был абстрактным, без наследников и без сериализации: полиморфизм через
  `oneOf` не работал вовсе.
- `enum`-схема со скалярным `default` определяла тип как `object`.

### Добавлено

- **OpenAPI 3.1** рядом с 3.0: `Version`, `webhooks`, `jsonSchemaDialect`, `info.summary`,
  `license.identifier`, `components.pathItems`, схема `mutualTLS`. Различия диалектов
  (`nullable` против типа-массива, исключающие границы) применяются автоматически.
- **Полный словарь JSON Schema 2020-12**: `const`, `if`/`then`/`else`, `prefixItems`,
  `contains`, `patternProperties`, `propertyNames`, `dependentRequired`, `dependentSchemas`,
  `unevaluated*`, `content*`, `$defs`, `$id`, `$anchor`, `$dynamicRef`, `$vocabulary`.
  Адресация везде по объектам: схема внутри `$defs` ссылается так же, как обычный компонент,
  а `$dynamicRef` принимает схему с `$dynamicAnchor`, а не строку.
- **Вывод в YAML** без внешних зависимостей, через подключаемый `Serialization\EncoderInterface`.
- `Builder::save()` и `Builder::encode()`.
- **Reference Object** с собственными `summary` и `description` (3.1).
- **Проверки инвариантов спецификации**: шаблон пути против path-параметров, операция без
  ответов, перевёрнутые границы, `multipleOf <= 0`, `discriminator` без композиции,
  взаимоисключающие поля. Неотрицательные границы объявлены как `int<0, max>` и ловятся
  PHPStan до запуска.
- Единый `OpenapiExceptionInterface` для всех исключений пакета.

### Сломано

Переименования:

| Было | Стало |
|---|---|
| `Components\SecuritySchemes\BaseHttpSecurityScheme` | `BasicHttpSecurityScheme` (без `bearerFormat`) |
| `Info\Contacts`, `Info::$contacts` | `Info\Contact`, `Info::$contact` |
| `Openapi::finsSecurity()` | `Openapi::findSecurity()` |
| `Components\Schemas\Object\Discriminator` | `Components\Schemas\Abstract\Discriminator` |
| `Components\Links\Link\Variable`, `Variables` | `Servers\Variable`, `Servers\Variables` |

Изменённые сигнатуры:

- `Builder::__construct()` требует имена файлов ключами массива и отклоняет позиционные
  аргументы. `Builder::save()` принимает `EncoderInterface` вместо флагов `json_encode`;
  `Builder::DEFAULT_JSON_FLAGS` переехал в `JsonEncoder::DEFAULT_FLAGS`.
- `prepareToSave('')` больше не добавляет ведущий слэш к именам файлов.
- `Components::$examples` — теперь `Components\Examples` из объектов `Example`, а не карта
  голых значений.
- `Content` и параметры со схемой: `$examples` разделён на `$example` (одно значение) и
  `$examples` (карта `Example`), они взаимоисключающи.
- `Callbacks` и `Openapi::$webhooks` принимают `PathItems`, а не `Paths`: там ключ — имя или
  runtime-выражение, а не шаблон пути.
- `Servers\Server` больше не принимает `enum` и `default` — это поля Server Variable.
  `Variable::$default` теперь `string`, как требует спецификация.
- `Parameters\Path\SchemaParameter` больше не принимает `allowEmptyValue` и `allowReserved`:
  по спецификации они только для query.
- `Server::toObject()` и `Servers::toArray()` больше не принимают `Process`.
- `Openapi::findExample()` принимает `Example`.

### Прочее

- `composer.json`: `type` исправлен с `project` на `library`.
- PHPStan максимального уровня со всеми дополнительными опциями, php-cs-fixer на
  `@PhpCsFixer:risky` + `@PHP83Migration`, CI на PHP 8.3 и 8.4.
- `ReadmeTest` исполняет каждый PHP-пример из README.

### Известные ограничения

- `Security Requirement Object` по спецификации ссылается на схемы того же документа,
  поэтому скоупы не ищутся в соседних файлах.
- Проверяются инварианты спецификации, а не смысл документа.

## [1.0.0]

Первый выпуск.
