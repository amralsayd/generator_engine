<?php

/**
 * @file
 * Hooks provided by the Generator Engine module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alters the entity and bundle definitions used by the generation engine.
 *
 * The definitions drive both the generation form and the engine itself.
 *
 * The module only ships generic example bundles (article, page, tags,
 * user). Use this hook from a site-specific module to register your own
 * content types, taxonomy vocabularies, or other bundles - and the fields
 * on them - without modifying this module's code.
 *
 * @param array $config
 *   An associative array keyed by entity-type group (e.g. 'node_type',
 *   'taxonomy_vocabulary', 'user', 'media_type', ...), each containing an
 *   array keyed by bundle machine name. Each bundle entry has the shape:
 *   @code
 *   [
 *     'type' => 'node',
 *     'entity_class' => '\Drupal\node\Entity\Node',
 *     'entity_type_property' => 'type',
 *     'entity_title_property' => 'title',
 *     'classNodes' => '',
 *     'classSingleNode' => '',
 *     'properties' => [
 *       'uid' => ['property' => 'custom', 'ref' => 'user'],
 *     ],
 *     'custom_fields' => [
 *       'field_example' => ['mode' => 'default', 'bind' => 'value'],
 *     ],
 *   ]
 *   @endcode
 *
 * @see \Drupal\generator_engine\HelpersService::getEntitiesConfig()
 */
function hook_generator_engine_entities_config_alter(array &$config) {
  // Register a custom "institutions" node bundle from a site-specific
  // module, instead of shipping it in the contrib module itself.
  $config['node_type']['institutions'] = [
    'type' => 'node',
    'entity_class' => '\Drupal\node\Entity\Node',
    'entity_type_property' => 'type',
    'entity_title_property' => 'title',
    'classNodes' => '',
    'classSingleNode' => '',
    'properties' => [
      'uid' => [
        'property' => 'custom',
        'ref' => 'user',
      ],
    ],
    'custom_fields' => [
      'field_sector_type' => [
        'mode' => 'default',
        'bind' => 'value',
      ],
    ],
  ];
}

/**
 * Alter the field values of a single entity before it is generated.
 *
 * Fired by the generation engine right before the entity object is created
 * and saved, once per generated entity. Use it to add, remove or rewrite
 * values in the structure that is passed to the entity ::create(), for
 * example to fill a field the engine cannot build on its own or to derive a
 * value from the other generated fields.
 *
 * @param array $data
 *   The values that are about to be passed to the entity class ::create().
 *   Keyed by property/field machine name, in the format expected by that
 *   entity type (e.g. 'title' => 'Article 1234', 'field_ref' => ['target_id'
 *   => 12]).
 * @param array $context
 *   Information about the entity being generated:
 *   - entity_type: the entity type id, e.g. 'node', 'taxonomy_term', 'user'.
 *   - bundle: the bundle machine name, e.g. 'article'.
 *   - fields_data: the submitted target entity data for this bundle.
 *   - fields_config: the entities configuration for this entity type group,
 *     as returned by HelpersService::getEntitiesConfig().
 *   - generator: the single node generator instance doing the work.
 *
 * @see \Drupal\generator_engine\Classes\SingleNodeGenerator::generateSingleNode()
 */
function hook_generator_engine_entity_data_alter(array &$data, array $context) {
  if ($context['entity_type'] !== 'node' || $context['bundle'] !== 'institutions') {
    return;
  }

  // Force a value the generation engine does not know how to build.
  $data['field_sector_type'] = 'public';

  // Derive a value from the other generated fields.
  if (!empty($data['title'])) {
    $data['field_short_name'] = substr($data['title'], 0, 10);
  }
}

/**
 * React to a single generated entity.
 *
 * Fired once per generated entity, right after it has been saved, so the
 * entity already has an id. Use it to post-process the result: attach extra
 * data, log it, index it, or generate related entities. The entity is passed
 * as an object, so changes made here must be saved by the implementation
 * itself.
 *
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   The generated entity, already saved.
 * @param array $context
 *   The same context as hook_generator_engine_entity_data_alter():
 *   entity_type, bundle, fields_data, fields_config and generator.
 *
 * @see hook_generator_engine_entity_data_alter()
 * @see \Drupal\generator_engine\Classes\SingleNodeGenerator::generateSingleNode()
 */
function hook_generator_engine_entity_generated(\Drupal\Core\Entity\EntityInterface $entity, array $context) {
  if ($context['entity_type'] !== 'node' || $context['bundle'] !== 'institutions') {
    return;
  }

  \Drupal::logger('my_module')->notice('Generated institution @id.', [
    '@id' => $entity->id(),
  ]);

  // Anything set here needs to be saved explicitly.
  $entity->setOwnerId(1);
  $entity->save();
}

/**
 * React to the full result of one entity type / bundle generation.
 *
 * Fired once per generated bundle, after all the entities requested for that
 * entity type / bundle have been generated. Use it when you need the whole
 * result at once - for example to relate the generated entities to each
 * other, or to build a report.
 *
 * Note that the batch generation path builds its operations up front, so
 * only the per-entity hooks above are fired there.
 *
 * @param array $items
 *   The generated items for this bundle, each one in the engine result
 *   format [entity_type => [bundle => entity_id]]:
 *   @code
 *   [
 *     ['node' => ['institutions' => 12]],
 *     ['node' => ['institutions' => 13]],
 *   ]
 *   @endcode
 * @param array $context
 *   Information about the generated bundle:
 *   - entity_type: the entity type id, e.g. 'node'.
 *   - bundle: the bundle machine name, e.g. 'institutions'.
 *   - count: the number of entities requested for this bundle.
 *   - target_entities: the submitted target entity data for this entity type
 *     group.
 *   - entities_configs: the entities configuration for this entity type
 *     group, as returned by HelpersService::getEntitiesConfig().
 *   - generator: the nodes generator instance doing the work.
 *
 * @see \Drupal\generator_engine\Classes\NodesGenerator::generateNodes()
 */
function hook_generator_engine_bundle_generated(array $items, array $context) {
  if ($context['entity_type'] !== 'node' || $context['bundle'] !== 'institutions') {
    return;
  }

  $ids = [];
  foreach ($items as $item) {
    $ids[] = $item[$context['entity_type']][$context['bundle']];
  }

  \Drupal::logger('my_module')->notice('Generated @count institutions: @ids.', [
    '@count' => count($ids),
    '@ids' => implode(', ', $ids),
  ]);
}

/**
 * @} End of "addtogroup hooks".
 */
