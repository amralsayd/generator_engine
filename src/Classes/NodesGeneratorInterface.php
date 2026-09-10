<?php

namespace Drupal\generator_engine\Classes;

/**
 * Generates a run of entities for one bundle.
 */
interface NodesGeneratorInterface {

  /**
   * Generates the configured number of entities directly.
   *
   * @param array $target_entities
   *   The generation statement.
   * @param array $entities_configs
   *   Entity/bundle generation configuration.
   *
   * @return array
   *   The generated items.
   */
  public function generateNodes($target_entities, $entities_configs);

  /**
   * Builds batch operations for the configured number of entities.
   *
   * @param array $target_entities
   *   The generation statement.
   * @param array $entities_configs
   *   Entity/bundle generation configuration.
   *
   * @return array
   *   Batch operations, each a [callback, arguments] pair.
   */
  public function generateNodesBatch($target_entities, $entities_configs);

}
