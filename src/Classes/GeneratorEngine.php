<?php

namespace Drupal\generator_engine\Classes;

/**
 * Runs generation statements directly, without the Batch API.
 */
class GeneratorEngine {

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
   * Generates every checked bundle in a target-entities statement.
   *
   * @param array $target_entities
   *   The generation statement.
   * @param array $entities_configs
   *   Entity/bundle generation configuration.
   * @param int|string|null $batch
   *   Existing batch node id to append to.
   * @param array $initial_references
   *   Reference data available to "ref|field" bindings.
   *
   * @return array
   *   ['batch_id' => mixed, 'items' => array] for the run.
   */
  public function execute($target_entities, $entities_configs, $batch = NULL, &$initial_references = []) {
    // re-structure the target entities in case have references
    // $initial_references = [];.
    if (!empty($target_entities['references'])) {
      $initial_references = $target_entities['references'];
      // unset($target_entities['references']);.
    }
    // Create generate batch node to store the result and the progress.
    $items = $this->generateProcess($target_entities, $entities_configs, $batch, $initial_references);

    // $this->storeBatch($items, $batch);
    $batch_node = [];
    return ['batch_id' => $batch_node, 'items' => $items];
  }

  /**
   * Runs numerically-keyed parts in order, threading references between them.
   *
   * @param array $parts
   *   Ordered generation statements, optionally preceded by a
   *   "references" part supplying seed data.
   * @param array $entities_configs
   *   Entity/bundle generation configuration.
   * @param int|string|null $batch
   *   Existing batch node id to append to.
   *
   * @return array
   *   ['batch_id' => mixed, 'items' => array] for the whole run.
   */
  public function executeDependant($parts, $entities_configs, $batch = NULL) {
    // re-structure the target entities in case have references
    // $initial_references = [];.
    if (!empty($parts['references'])) {
      $initial_references = $parts['references'];
      // unset($parts['references']);.
    }
    // Create generate batch node to store the result and the progress.
    $items = [];
    foreach ($parts as $part_key => $target_entities) {
      if ($part_key === 'references') {
        continue;
      }

      $generated_items = $this->generateProcess($target_entities, $entities_configs, $batch, $initial_references);
      $items = array_merge($items, $generated_items);
    }

    // $this->storeBatch($items, $batch);
    $batch_node = [];
    return ['batch_id' => $batch_node, 'items' => $items];
  }

  /**
   * Generates one statement and collects the resulting entity ids.
   *
   * @param array $target_entities
   *   The generation statement.
   * @param array $entities_configs
   *   Entity/bundle generation configuration.
   * @param int|string|null $batch
   *   Existing batch node id to append to.
   * @param array $initial_references
   *   Reference data available to "ref|field" bindings.
   *
   * @return array
   *   The generated items.
   */
  public function generateProcess($target_entities, $entities_configs, $batch = NULL, &$initial_references = []) {
    $items = [];
    foreach ($target_entities as $target_entity_key => $target_entity) {
      if ($target_entity_key === 'references') {
        continue;
      }
      // learner_subscription ,license.
      foreach ($target_entity as $target_entity_bundle_key => $target_entity_bundle) {
        $count = 1;
        $flag_rebind = FALSE;
        $result_index = NULL;
        if (!empty($target_entity_bundle['references'])) {
          $count = $target_entity_bundle['references']['count'];
          $flag_rebind = TRUE;
          if (!empty($target_entity_bundle['references']['result_index'])) {
            $result_index = $target_entity_bundle['references']['result_index'];
          }
        }
        $request_entity = $target_entity;
        for ($iter = 0; $iter < $count; $iter++) {
          if ($flag_rebind) {
            $request_entity = [$target_entity_bundle_key => $this->rebindTargetEntity($target_entity_bundle, $initial_references, $iter)];
          }
          if ($target_entity_bundle['check']) {
            $class = '\Drupal\generator_engine\Classes\EntitiesNodesGenerator';
            if (!empty($entities_configs[$target_entity_key][$target_entity_bundle_key]['classNodes'])) {
              $class = $this->basePathNodesGenerator . $entities_configs[$target_entity_key][$target_entity_bundle_key]['classNodes'];
            }

            $obj = new $class($entities_configs[$target_entity_key][$target_entity_bundle_key]['type'],
              $target_entity_bundle_key,
              $target_entity_bundle['count'],
              $request_entity);

            $generated_items = $obj->generateNodes($request_entity, $entities_configs[$target_entity_key]);

            if (!empty($result_index)) {
              foreach ($generated_items as $generated_item) {
                $initial_references[$result_index][] = current($generated_item);
              }
            }
            $items = array_merge($items, $generated_items);
          }
        }
      }
    }
    return $items;
  }

  /**
   * Resolves "ref|field" bindings in a statement against reference data.
   *
   * Supports "ref|field" (current iteration), "ref!|field" (always the
   * first item) and "ref$|field" (a random item).
   *
   * @param array $target_entity
   *   The statement to rebind.
   * @param array $initial_references
   *   Reference data keyed by reference name.
   * @param int $iter
   *   The current iteration index.
   *
   * @return array
   *   The statement with its references resolved.
   */
  public function rebindTargetEntity($target_entity, $initial_references, $iter = 0) {
    $new_target_entity = $target_entity;
    foreach ($target_entity['fields'] as $key => $value) {
      // Fixed value => first one.
      if (is_string($value) && str_contains($value, "!|")) {
        $indexes = explode('!|', $value);
        $new_target_entity['fields'][$key] = $initial_references[$indexes[0]][0][$indexes[1]];
      }
      // Random value.
      elseif (is_string($value) && str_contains($value, "$|")) {
        $indexes = explode('$|', $value);
        $ransom_index = rand(0, count($initial_references[$indexes[0]]) - 1);
        $new_target_entity['fields'][$key] = $initial_references[$indexes[0]][$ransom_index][$indexes[1]];
      }
      // Normal iterator.
      elseif (is_string($value) && str_contains($value, "|")) {
        $indexes = explode('|', $value);
        $new_target_entity['fields'][$key] = $initial_references[$indexes[0]][$iter][$indexes[1]];
      }
    }

    foreach ($target_entity['properties'] as $key => $value) {
      if (is_string($value) && str_contains($value, "|")) {
        $indexes = explode('|', $value);
        $new_target_entity['properties'][$key] = $initial_references[$indexes[0]][$iter][$indexes[1]];
      }
    }
    return $new_target_entity;
  }

}
