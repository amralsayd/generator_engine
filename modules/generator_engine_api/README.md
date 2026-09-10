# Generator Engine API

Exposes the `generator_engine` generation pipeline (the same one behind the
"Using JSON" / "Using YAML" admin forms and the `generator_engine:generate` Drush
command) over HTTP. Every endpoint **first validates the payload syntax** and returns the
outcome; on a syntax error nothing is generated.

## Enable / disable

- Enable the module: `drush en generator_engine_api` (pulls in the `basic_auth` core
  module). Enable `generator_engine_api_rest` as well for the REST resource endpoints.
- **Off by default even when enabled.** Tick **"Enable JSON/YAML generation APIs"** on
  `admin/generate/config` (config key `generator_engine.settings:enable_apis`). While the
  toggle is off every endpoint returns HTTP 403 `{"status":"disabled"}`.
- Uninstalling this module flips that toggle back off.

## Access

- Requires the `administer generator engine` permission.
- Plain routes accept `basic_auth` or `cookie`. (The REST resources in the optional
  `generator_engine_api_rest` sub-module are configured for `basic_auth` + `cookie` too.)
- These routes create content and user accounts, so cookie-authenticated
  requests must send an `X-CSRF-Token` header, obtainable from `/session/token`.
  Basic Auth requests carry no session and do not need one.
- Requests are rejected with `422 count_exceeded` when any `count` in the
  payload is above `generator_engine.settings:max_generation_count`.
- Add `?validate_only=1` to any plain route to stop after validation
  (`{"status":"validated"}`).

## Response body shape

```json
{
  "status": "success | validated | disabled | empty_payload | invalid_json | invalid_yaml | not_array | no_target_entities | missing_payload | no_file",
  "valid": true,
  "format": "json",
  "validate_only": false,
  "message": "Generation finished. 1 item(s) generated.",
  "errors": [],
  "generated": { "count": 1, "batch_id": null, "items_by_type": { "node": { "article": 1 } } }
}
```

## Plain routes (raw body, Basic Auth)

| Method + path | Body |
| --- | --- |
| `POST /api/generator-engine/json` | raw JSON text |
| `POST /api/generator-engine/yaml` | raw YAML text |
| `POST /api/generator-engine/file` | `multipart/form-data` `file` upload (format from extension, override with `?format=`) or a raw body |

```
curl -u admin:admin -X POST https://SITE/api/generator-engine/json \
  -H 'Content-Type: application/json' \
  --data '[{"node_type":{"article":{"check":1,"count":"1","fields":{"title":"api article"}}}}]'

curl -u admin:admin -X POST https://SITE/api/generator-engine/yaml \
  -H 'Content-Type: text/plain' \
  --data '- node_type:
    article:
      check: 1
      count: "1"
      fields:
        title: "api article"'

curl -u admin:admin -X POST https://SITE/api/generator-engine/file \
  -F 'file=@web/modules/custom/generator_engine/assets/samples/sample-target-entities-article-tags.yml'
```

## REST resources

The REST‑style equivalents (`POST /api/generator-engine/rest/{json,yaml,file}`) live in
the separate **`generator_engine_api_rest`** sub‑module — see
`../generator_engine_api_rest/README.md`. They share this module's
`generator_engine_api.processor` back end and the same `enable_apis` toggle.

## Layout

| Path | Purpose |
| --- | --- |
| `src/GenerateApiService.php` (`generator_engine_api.processor`) | Validation + dispatch into `HelpersService::generateFromArray()`. Shared by every endpoint, including the REST sub‑module. |
| `src/Controller/GenerateApiController.php` | The three plain Basic-Auth routes. |
| `../generator_engine_api_rest/` | The `@RestResource` plugins + their `rest.resource.*` config. |
