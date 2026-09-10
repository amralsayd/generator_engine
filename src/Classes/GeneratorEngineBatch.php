<?php

namespace Drupal\generator_engine\Classes;

use Drupal\node\Entity\Node;

/**
 * Runs generation statements through the Batch API.
 */
class GeneratorEngineBatch {

  /**
   * Namespace prefix for per-entity nodes generator classes.
   *
   * @var string
   */
  public $basePathNodesGenerator = '\Drupal\generator_engine\Classes\Entities\\';

  /**
   * Parameters carried between generation steps.
   *
   * @var array
   */
  public $parameters;

  /**
   * Builds the batch operations for a target-entities statement.
   *
   * @param array $target_entities
   *   The generation statement.
   * @param array $entities_configs
   *   Entity/bundle generation configuration.
   * @param int|string|null $batch
   *   Existing batch node id to append to.
   *
   * @return array
   *   Batch operations, each a [callback, arguments] pair.
   */
  public function execute($target_entities, $entities_configs, $batch = NULL) {
    // Create generate batch node to store the result and the progress.
    $items = [];
    foreach ($target_entities as $target_entity_key => $target_entity) {
      foreach ($target_entity as $target_entity_bundle_key => $target_entity_bundle) {
        if ($target_entity_bundle['check']) {
          $class = '\Drupal\generator_engine\Classes\EntitiesNodesGenerator';
          if (!empty($entities_configs[$target_entity_key][$target_entity_bundle_key]['classNodes'])) {
            $class = $this->basePathNodesGenerator . $entities_configs[$target_entity_key][$target_entity_bundle_key]['classNodes'];
          }

          $obj = new $class($entities_configs[$target_entity_key][$target_entity_bundle_key]['type'],
                $target_entity_bundle_key,
                $target_entity_bundle['count'],
                $target_entity);

          $generated_items = $obj->generateNodesBatch($target_entity, $entities_configs[$target_entity_key]);
          $items = array_merge($items, $generated_items);
        }
      }
    }
    return ['batch_id' => NULL, 'items' => $items];
  }

  /**
   * Batch callback that files the generated entities under a batch node.
   *
   * @param int|string|null $batch
   *   Existing batch node id, or NULL to create one.
   * @param array|\ArrayAccess $context
   *   The batch context.
   */
  public function storeBatch($batch, &$context) {
    $items = $context['results']['generated_entities'];
    $node = NULL;

    $nodes_items = [];
    $users_items = [];
    foreach ($items as $item) {
      if (key($item) == 'user') {
        $users_items[] = ['target_id' => current($item)];
      }
      else {
        $nodes_items[] = ['target_id' => current($item)];
      }
    }

    if (!empty($batch)) {
      $node = Node::load($batch);
    }
    else {
      $data = [
        'type' => 'generated_batch',
        'title' => 'generated_batch' . rand(1000, 9999),
      ];
      $node = Node::create($data);
    }

    $node->field_gb_nodes_items = array_merge($node->get('field_gb_nodes_items')->getValue(), $nodes_items);
    $node->field_gb_users_items = array_merge($node->get('field_gb_users_items')->getValue(), $users_items);
    $node->save();

    $context['results']['generated_batch'] = $node->id();

    return $node->id();
  }

}
