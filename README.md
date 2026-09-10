# Generator Engine

## Introduction

Generator Engine creates dummy content entities — nodes, users and taxonomy
terms — so developers and QA can populate a site quickly. It can generate from
an admin form, from a JSON or YAML document, from Drush, or over HTTP.

You choose which entity types and bundles may be generated, and which of their
fields take part, then submit a generation document describing what to create.

**This module creates arbitrary content and user accounts and writes uploaded
files. Use it on development and QA sites only, and uninstall it before going
to production.**


## Installation

Install as you would normally install a contributed Drupal module. See
[Installing modules](https://www.drupal.org/docs/extending-drupal/installing-modules).

The project ships one required module and three optional sub-modules:

| Module | Purpose |
| --- | --- |
| `generator_engine` | The engine, the admin forms and the Drush commands. |
| `generator_engine_batch` | Adds a "Generated batch" content type that collects the entities produced by each run. |
| `generator_engine_api` | JSON/YAML/file HTTP endpoints for the generation pipeline. |
| `generator_engine_api_rest` | The same endpoints as REST resource plugins. Requires `generator_engine_api`. |

## Configuration

1. Grant the **Administer Generator Engine** permission. It is marked as a
   restricted permission: it allows creating arbitrary content and user
   accounts, so grant it only to trusted roles on non-production sites.
2. Visit **/admin/generate/config** and select the entity types and bundles
   that may be generated.
3. Visit **/admin/generate/config/forms** to choose which form displays drive
   the per-bundle generation form.

Settings live in `generator_engine.settings`:

| Key | Default | Meaning |
| --- | --- | --- |
| `content_entities` | article, page, tags, user | Entity types and bundles that may be generated. |
| `max_generation_count` | `500` | Largest `count` a single generation statement may request. |
| `enable_apis` | `false` | Master switch for the HTTP endpoints. |

Generation is synchronous, so `max_generation_count` bounds how long a single
request can run. Raise it only if you understand the cost.

## Generating entities

### Using PHP Code

```php
    $target_entities = [
      'node_type' => [
        "article" => [ // <== bundle name like article or basic page
          "check" => 1, // <== flag to execute the generation for this statement or not
          "count" => 5, // <== number of generated entities
          "fields" => [// <== fields (name, values) that will used in the generation process. Generated from the 'custom_fields' section in generation form
            "title" => '%lorem_title',
            "body" => '%lorem_paragraph',
            "field_tags" => "!random_single", // <== single value of list 
            "field_image" => '$image',
          ],
          "properties" => [// <== fields (name, values) that will used in the generation process. Generated from the 'proprties' section in generation form
            "uid" => null, 
            "status" => 0, // <== publish status of the node
          ],
        ],
        "page" => [ // <== another bundle name like article or basic page
          "check" => 1,
          "count" => 3, 
          "fields" => [
            "title" => '%lorem_title',
            "body" => '%lorem_paragraph',
          ],
        ],
      ],
      "taxonomy_vocabulary" => [ // <== another entity type
        "tags" => [  // target vocabulary
          "check" => 1,
          "count" => 2,
          "fields" => [
            "name" => "%lorem_title",
          ],
          "properties" => [],
        ]
      ],
      "user" => [ // user entity type
        "user" => [
          "check" => 1,
          "count" => 5,
        ],
      ],
    ];
    $generateHelpers = \Drupal::service('generator_engine.helpers');
    $result = $generateHelpers->generateEngine($target_entities); 
```

### From the UI

- **/admin/generate/entities** — a per-bundle form built from your field
  configuration.
- **/admin/generate/json/highlighter** — submit a JSON generation document.
- **/admin/generate/yaml/highlighter** — submit a YAML generation document.

### From Drush

```
drush generator_engine:generate <file>
```

Runs the same pipeline as the JSON and YAML forms. The format is detected from
the file extension, or forced with `--format=json|yaml|php`. Pass `-` to read
from stdin, which requires `--format`.

| Option | Meaning |
| --- | --- |
| `--format` | `auto` (default), `json`, `yaml` or `php`. |
| `--batch` | Node ID of an existing "Generated batch" node to append to. |
| `--exclude` | Filter against the configured allow-list. `--no-exclude` disables. |

Aliases: `ge-generate`, `ge-gen`.

```
drush generator_engine:generate assets/samples/sample-target-entities.json
drush generator_engine:generate assets/samples/sample-target-entities.yml
drush generator_engine:generate assets/samples/sample-target-entities.php --format=php
cat data.yml | drush generator_engine:generate - --format=yaml
```

A second command seeds users, taxonomy terms and content authored by and
tagged with them:

```
drush generator_engine:seed_users_tags_content --users_count=2 --tags_count=6
```

Alias: `ge-seed-utc`.

The `php` format executes the named file so it can define `$target_entities`.
It is available from Drush only and is never selected by the HTTP endpoints.

### Over HTTP

Enable `generator_engine_api`, then turn on **Enable JSON/YAML generation
APIs** at `/admin/generate/config`. The endpoints accept `POST` and require the
`administer generator engine` permission:

- `/api/generator-engine/json`
- `/api/generator-engine/yaml`
- `/api/generator-engine/file`

Enabling `generator_engine_api_rest` additionally exposes them as REST resource
plugins under `/api/generator-engine/rest/{json,yaml,file}`.

Both Basic Auth and cookie authentication are accepted. Cookie-authenticated
requests must send an `X-CSRF-Token` header, which you can obtain from
`/session/token`. Basic Auth requests do not need one.

See `modules/generator_engine_api/README.md` and
`modules/generator_engine_api_rest/README.md` for the request and response
shapes.

## The generation document

A generation document is a map of entity type to bundle to a statement:

```yaml
node_type:
  article:
    check: 1          # generate this bundle
    count: '3'        # how many
    fields:
      title: 'My article'
      field_tags: '!random_single'
    properties:
      uid: null       # author; null means the current user
```

`fields` holds values for the bundle's configured fields; `properties` holds
entity properties such as `uid` and `status`.

### Field value directives

| Directive | Effect |
| --- | --- |
| `!random_single` | Pick one random valid value for the field. |
| `!random_multiple` | Pick between one and five random valid values. |
| `$<ext>` | Attach `assets/files/sample-file.<ext>` as a managed file. |
| `+text` | Append " [text]" to the generated value. |
| `#key` | Copy another field's value from the same statement. |
| `%name` | Call an allow-listed value generator (see below). |

`%name` resolves against a fixed allow-list — `lorem`, `lorem_paragraph` and
`random_url` — declared by `SingleNodeGenerator::valueGenerators()`. Any other
name is treated as a literal string and never executed. Subclasses may add
entries, but must never resolve a callable from submitted input.

### Dependent statements

A numerically-keyed document runs its parts in order and threads the results
between them, so later statements can reference entities created earlier. An
optional `references` part supplies seed data:

| Binding | Resolves to |
| --- | --- |
| `ref\|field` | The value for the current iteration. |
| `ref!\|field` | Always the first item. |
| `ref$\|field` | A random item. |

Worked examples of every format are in `assets/samples/`:

- `sample-target-entities.{json,yml,php}` — a basic statement.
- `sample-target-entities-article-tags.{json,yml,php}` — fields and references.
- `sample-target-entities-dependant.{json,yml,php}` — ordered dependent parts.

## Extending

Four alter hooks let another module register its own bundles and fields, adjust
generated values, and react to saved entities. They are documented with
examples in `generator_engine.api.php`:

- `hook_generator_engine_entities_config_alter()`
- `hook_generator_engine_entity_data_alter()`
- `hook_generator_engine_entity_generated()`
- `hook_generator_engine_bundle_generated()`

To generate a bundle that needs special handling, declare a `class_nodes` and
`class_single_node` for it and extend `NodesGenerator` and
`SingleNodeGenerator`, as `EntityUsersGenerator` and
`EntityUserSingleGenerator` do for user accounts.


### Optional: syntax highlighting

The "Using JSON" and "Using YAML" forms use [CodeMirror] for syntax
highlighting when it is available. The library is not bundled; without it the
forms fall back to a plain textarea and remain fully usable.

To install it, add asset-packagist to your project's root `composer.json` and
map the package into `libraries/`:

```json
"repositories": [
    { "type": "composer", "url": "https://packages.drupal.org/8" },
    { "type": "composer", "url": "https://asset-packagist.org" }
],
"extra": {
    "installer-types": ["npm-asset"],
    "installer-paths": {
        "web/libraries/{$name}": ["type:npm-asset"]
    }
}
```

then `composer require oomphinc/composer-installers-extender npm-asset/codemirror`.

[CodeMirror]: https://codemirror.net/5/

## Maintainers

- Amr Elsayed — add your drupal.org profile link here before submitting.
