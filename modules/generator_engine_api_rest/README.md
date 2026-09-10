# Generator Engine API — REST

`@RestResource` plugins for the Generator Engine endpoints. Enable this in addition to
`generator_engine_api` when you want the REST‑style resources alongside (or instead of)
the plain routing‑file endpoints; both flavours share the same
`generator_engine_api.processor` back end and the same
`generator_engine.settings:enable_apis` master toggle.

## REST resources (JSON envelope, Basic Auth)

The `rest.resource.*` config ships in this module's `config/install/`, so the resources
are live as soon as the module is enabled. Access requires the `administer generator engine`
permission; each resource is configured for `basic_auth` + `cookie`.

| Resource id + path | Envelope |
| --- | --- |
| `generator_engine_json` — `POST /api/generator-engine/rest/json` | `{"payload":"<json text>","validate_only":false}` |
| `generator_engine_yaml` — `POST /api/generator-engine/rest/yaml` | `{"payload":"<yaml text>","validate_only":false}` |
| `generator_engine_file` — `POST /api/generator-engine/rest/file` | `{"filename":"data.yml","payload":"<file text>","format":"yaml"}` |

REST request bodies are json/xml only, so YAML / file contents travel as the `payload`
string inside the JSON envelope. Response body shape is identical to the plain routes —
see `../generator_engine_api/README.md`.

```
curl -u admin:admin -X POST 'https://SITE/api/generator-engine/rest/yaml?_format=json' \
  -H 'Content-Type: application/json' \
  --data '{"payload":"- node_type:\n    article:\n      check: 1\n      count: \"1\"\n      fields:\n        title: rest yaml article"}'
```

## Layout

| Path | Purpose |
| --- | --- |
| `src/Plugin/rest/resource/GenerateRestResourceBase.php` | Shared base — pulls `generator_engine_api.processor`, pins access to `administer generator engine`, unwraps the `{payload, validate_only}` envelope. |
| `src/Plugin/rest/resource/Generate{Json,Yaml,File}Resource.php` | One `@RestResource` per format. |
| `config/install/rest.resource.generator_engine_*.yml` | Enables each resource with `basic_auth` + `cookie`. |
