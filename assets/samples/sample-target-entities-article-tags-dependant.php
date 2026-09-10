<?php

/**
 * Sample dependant structure for:
 *   - taxonomy_term  : vocabulary "tags"
 *   - node           : bundle "article" (references the generated tags)
 *
 * Usage:
 *   $generateHelpers = \Drupal::service('generator_engine.helpers');
 *   $result = $generateHelpers->generateEngineDependant($target_entities);
 *
 * Reference binding (see GeneratorEngine::rebindTargetEntity):
 *   "ref|field"   => value of the current iteration    ($initial_references[ref][$iter][field])
 *   "ref!|field"  => fixed value, always the first item ($initial_references[ref][0][field])
 *   "ref$|field"  => random item                        ($initial_references[ref][rand][field])
 * Generated entities are stored under "result_index" as [bundle_key => id],
 * so they are referenced through "result_index|bundle_key".
 */

$target_entities["references"] = [
  "tags_info" => [
    ['name' => 'Technology'],
    ['name' => 'Science'],
    ['name' => 'Education'],
    ['name' => 'Innovation'],
  ],
  "articles_info" => [
    ['title_en' => 'The Future of Learning', 'body_en' => 'The future of learning is the future of the future'],
    ['title_en' => 'Digital Transformation', 'body_en' => 'The future of learning is the future of the future'],
    ['title_en' => 'Artificial Intelligence', 'body_en' => 'The future of learning is the future of the future'],
    ['title_en' => 'Skills for Tomorrow', 'body_en' => 'The future of learning is the future of the future'],
  ],
];

/**
 * Create the taxonomy "tags" terms.
 * Each generated term is stored in the "tags" result index as ['tags' => tid].
 */
$target_entities[] = [
  "taxonomy_vocabulary" => [
    "tags" => [
      "check" => 1,
      "count" => 1,
      "references" => ['count' => 4, 'result_index' => 'tags'],
      "fields" => [
        "name" => "tags_info|name",
      ],
      "properties" => [],
    ]
  ]
];

/**
 * Create the "article" nodes referencing the generated tags.
 * field_tags points to the term created at the same iteration ("tags|tags").
 */
$target_entities[] = [
  "node_type" => [
    "article" => [
      "check" => 1,
      "count" => 1,
      "references" => ['count' => 4, 'result_index' => 'articles'],
      "fields" => [
        "title" => "articles_info|title_en",
        "body" => "articles_info|body_en", // for drupal 11 change this to "field_body"
        "field_image" => '$image', // for drupal 11 change this to "field_media_image"
        "field_tags" => "tags|tags",
      ],
      "properties" => [
        "uid" => null,
      ],
    ]
  ]
];
