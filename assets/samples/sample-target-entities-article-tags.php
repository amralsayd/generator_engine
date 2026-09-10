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
$target_entities = [
  'node_type' => [
    "article" => [ // <== bundle name like article or basic page
      "check" => 1, // <== flag to execute the generation for this statement or not
      "count" => 5, // <== number of generated entities
      "fields" => [// <== fields (name, values) that will used in the generation process. Generated from the 'custom_fields' section in generation form
        "title" => '%lorem_title',
        "body" => '%lorem_paragraph',
        "field_tags" => "!random_single", // <== single value of list 
        "field_image" => '$image', // for drupal 11 change this to "field_media_image"
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
        "body" => '%lorem_paragraph', // for drupal 11 change this to "field_body"
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
