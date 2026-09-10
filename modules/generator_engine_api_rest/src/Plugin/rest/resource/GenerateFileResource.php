<?php

namespace Drupal\generator_engine_api_rest\Plugin\rest\resource;

/**
 * REST resource that generates entities from an uploaded file's contents.
 *
 * The file's text content travels inside the JSON envelope's "payload" string
 * (REST bodies are json/xml only). The format is resolved from the "filename"
 * extension (yml/yaml => yaml, otherwise json) or an explicit "format" field.
 *
 * @RestResource(
 *   id = "generator_engine_file",
 *   label = @Translation("Generator Engine: file"),
 *   uri_paths = {
 *     "create" = "/api/generator-engine/rest/file"
 *   }
 * )
 */
class GenerateFileResource extends GenerateRestResourceBase {

  /**
   * Responds to POST requests.
   *
   * @param mixed $data
   *   Request body shaped as {"filename": "data.yml",
   *   "payload": "<file text>", "validate_only": false}. An optional
   *   "format" ("json" or "yaml") overrides extension detection.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The generation / validation result.
   */
  public function post($data) {
    $format = 'json';
    if (is_array($data)) {
      if (!empty($data['format'])) {
        $format = ($data['format'] === 'yaml') ? 'yaml' : 'json';
      }
      elseif (!empty($data['filename'])) {
        $ext = strtolower(pathinfo((string) $data['filename'], PATHINFO_EXTENSION));
        $format = ($ext === 'yml' || $ext === 'yaml') ? 'yaml' : 'json';
      }
    }

    return $this->handlePost($data, $format);
  }

}
