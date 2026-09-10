<?php

namespace Drupal\generator_engine_api_rest\Plugin\rest\resource;

/**
 * REST resource that generates entities from a YAML payload.
 *
 * The YAML text travels inside the JSON envelope's "payload" string, because
 * the REST module only deserializes json/xml request bodies.
 *
 * @RestResource(
 *   id = "generator_engine_yaml",
 *   label = @Translation("Generator Engine: YAML"),
 *   uri_paths = {
 *     "create" = "/api/generator-engine/rest/yaml"
 *   }
 * )
 */
class GenerateYamlResource extends GenerateRestResourceBase {

  /**
   * Responds to POST requests.
   *
   * @param mixed $data
   *   Request body shaped as
   *   {"payload": "<yaml text>", "validate_only": false}.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The generation / validation result.
   */
  public function post($data) {
    return $this->handlePost($data, 'yaml');
  }

}
