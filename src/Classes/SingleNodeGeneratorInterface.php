<?php

namespace Drupal\generator_engine\Classes;

/**
 * Generates a single entity.
 */
interface SingleNodeGeneratorInterface {

  /**
   * Builds and saves one entity.
   *
   * @return array
   *   [entity_type => [bundle => entity_id]] for the saved entity.
   */
  public function generateSingleNode();

}
