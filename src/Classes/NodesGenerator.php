<?php

namespace Drupal\generator_engine\Classes;

/**
 * Generates the requested number of entities for a single bundle.
 */
abstract class NodesGenerator implements NodesGeneratorInterface {

  /**
   * Namespace prefix for per-entity single generator classes.
   *
   * @var string
   */
  public $basePathSingleNodeGenerator = 'Drupal\generator_engine\Classes\Entities\Single\\';

  /**
   * The entity type being generated.
   *
   * @var string
   */
  public $contentType = 'node';

  /**
   * The bundle being generated.
   *
   * @var string
   */
  public $contentBundle;

  /**
   * How many entities to generate.
   *
   * @var int
   */
  public $numberOfEntities;

  /**
   * Submitted field values for the bundle.
   *
   * @var array
   */
  public $fieldsData;

  public function __construct($content_type, $content_bundle, $number_of_entities, $fields_data) {
    $this->contentType = $content_type;
    $this->contentBundle = $content_bundle;
    $this->numberOfEntities = $number_of_entities;
    $this->fieldsData = $fields_data;
  }

  /**
   * {@inheritdoc}
   */
  public function generateNodes($target_entities, $entities_configs) {
    $nodes = [];
    for ($i = 0; $i < $this->numberOfEntities; $i++) {
      $class = '\Drupal\generator_engine\Classes\EntitySingleNodeGenerator';
      if (!empty($entities_configs[$this->contentBundle]['classSingleNode'])) {
        $class = $this->basePathSingleNodeGenerator . $entities_configs[$this->contentBundle]['classSingleNode'];
      }

      $obj = new $class($entities_configs[$this->contentBundle]['type'],
                $this->contentBundle,
                $target_entities,
                $entities_configs);
      $nodes[] = $obj->generateSingleNode();
    }

    // Let other modules access the whole result of this entity type /
    // bundle generation.
    // @see hook_generator_engine_bundle_generated()
    $context = [
      'entity_type' => $this->contentType,
      'bundle' => $this->contentBundle,
      'count' => $this->numberOfEntities,
      'target_entities' => $target_entities,
      'entities_configs' => $entities_configs,
      'generator' => $this,
    ];
    \Drupal::moduleHandler()->invokeAll('generator_engine_bundle_generated', [$nodes, $context]);

    return $nodes;
  }

  /**
   * {@inheritdoc}
   */
  public function generateNodesBatch($target_entities, $entities_configs) {
    $nodes = [];
    for ($i = 0; $i < $this->numberOfEntities; $i++) {
      $class = '\Drupal\generator_engine\Classes\EntitySingleNodeGenerator';
      if (!empty($entities_configs[$this->contentBundle]['classSingleNode'])) {
        $class = $this->basePathSingleNodeGenerator . $entities_configs[$this->contentBundle]['classSingleNode'];
      }

      $obj = new $class($entities_configs[$this->contentBundle]['type'],
                $this->contentBundle,
                $target_entities,
                $entities_configs);

      $nodes[] = [
        'callback_function' => $class . "::generateSingleNodeBatch",
        'params' => [
          'entity_type' => $entities_configs[$this->contentBundle]['type'],
          'bundle_type' => $this->contentBundle,
          'target_entities' => $target_entities,
          'entities_configs' => $entities_configs,
          'obj' => $obj,
        ],
      ];
    }
    return $nodes;
  }

}
