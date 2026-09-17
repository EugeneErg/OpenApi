# Sources

The real specifications the full read → write round trip is checked on. The comparison is
by meaning: the allowances are listed in `tests/Support/SemanticDiff.php`.

| File | Source | Licence |
|---|---|---|
| `oai-30-*.yaml`, `oai-31-*.yaml` | [OAI/learn.openapis.org](https://github.com/OAI/learn.openapis.org/tree/main/examples), the `v3.0` and `v3.1` directories | CC BY 4.0, © OpenAPI Initiative |
| `swagger-petstore-3.yaml` | [swagger-api/swagger-petstore](https://github.com/swagger-api/swagger-petstore/blob/master/src/main/resources/openapi.yaml) | Apache 2.0, © SmartBear Software |
| `synthetic-integer-like-names.json` | written by hand: names such as `7` and `-1` in every map of the document | — |

The large public specifications (GitHub, Stripe, Twilio, Asana) are too big to keep here;
`composer real-world` downloads and checks those.
