<?php

namespace Drupal\generator_engine_api_rest\Plugin\rest\resource;

/**
 * REST resource that generates entities from a JSON payload.
 *
 * @RestResource(
 *   id = "generator_engine_json",
 *   label = @Translation("Generator Engine: JSON"),
 *   uri_paths = {
 *     "create" = "/api/generator-engine/rest/json"
 *   }
 * )
 */
class GenerateJsonResource extends GenerateRestResourceBase {

  /**
   * Responds to POST requests.
   *
   * @param mixed $data
   *   Request body shaped as
   *   {"payload": "<json text>", "validate_only": false}.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The generation / validation result.
   */
  public function post($data) {
    return $this->handlePost($data, 'json');
  }

}
