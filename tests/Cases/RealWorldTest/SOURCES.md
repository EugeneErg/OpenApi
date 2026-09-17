# Источники

Настоящие спецификации, на которых проверяется полный круг «чтение → запись».
Сравнение смысловое: допущения перечислены в `tests/Support/SemanticDiff.php`.

| Файл | Источник | Лицензия |
|---|---|---|
| `oai-30-*.yaml`, `oai-31-*.yaml` | [OAI/learn.openapis.org](https://github.com/OAI/learn.openapis.org/tree/main/examples), каталоги `v3.0` и `v3.1` | CC BY 4.0, © OpenAPI Initiative |
| `swagger-petstore-3.yaml` | [swagger-api/swagger-petstore](https://github.com/swagger-api/swagger-petstore/blob/master/src/main/resources/openapi.yaml) | Apache 2.0, © SmartBear Software |
| `synthetic-integer-like-names.json` | составлен вручную: имена вида `7` и `-1` во всех картах документа | — |

Крупные публичные спецификации (GitHub, Stripe, Twilio, Asana) слишком велики, чтобы
хранить их здесь; их скачивает и проверяет `composer real-world`.
