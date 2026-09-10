<?php

namespace Drupal\generator_engine_api;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\generator_engine\HelpersService;

/**
 * Shared back end for the JSON/YAML generation HTTP endpoints.
 *
 * Both the plain Basic-Auth routes (GenerateApiController) and the REST
 * resource plugins in the generator_engine_api_rest sub-module funnel into
 * ::process(), so syntax validation and the dispatch into
 * HelpersService::generateFromArray() live in exactly one place - the same
 * pipeline the "Using JSON"/"Using YAML" admin forms and the
 * generator_engine:generate Drush command run.
 */
class GenerateApiService {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The generator_engine helpers service.
   *
   * @var \Drupal\generator_engine\HelpersService
   */
  protected $helpers;

  /**
   * Constructs a GenerateApiService.
   */
  public function __construct(ConfigFactoryInterface $config_factory, HelpersService $helpers) {
    $this->configFactory = $config_factory;
    $this->helpers = $helpers;
  }

  /**
   * Whether the generation APIs are switched on in configuration.
   *
   * @return bool
   *   TRUE when an admin has enabled the feature on the config form.
   */
  public function isEnabled() {
    return (bool) $this->configFactory->get('generator_engine.settings')->get('enable_apis');
  }

  /**
   * Validates a raw payload and, when valid, runs the generation pipeline.
   *
   * @param string $raw
   *   The raw JSON or YAML text.
   * @param string $format
   *   Either 'json' or 'yaml'.
   * @param bool $validate_only
   *   When TRUE, stop after syntax validation and generate nothing.
   *
   * @return array
   *   ['http_code' => int, 'body' => array] - see the class docblock / README
   *   for the body shape.
   */
  public function process($raw, $format, $validate_only = FALSE) {
    $format = ($format === 'yaml') ? 'yaml' : 'json';

    $body = [
      'status' => NULL,
      'valid' => NULL,
      'format' => $format,
      'validate_only' => (bool) $validate_only,
      'message' => '',
      'errors' => [],
      'generated' => NULL,
    ];

    if (!$this->isEnabled()) {
      return $this->result(403, [
        'status' => 'disabled',
        'message' => 'The Generator Engine APIs are disabled. Enable them on the "Generator Engine Configurations" form.',
      ] + $body);
    }

    if (!is_string($raw) || trim($raw) === '') {
      return $this->result(400, [
        'status' => 'empty_payload',
        'message' => 'The request body is empty.',
      ] + $body);
    }

    // Syntax validation.
    if ($format === 'yaml') {
      try {
        $data = Yaml::decode($raw);
      }
      catch (InvalidDataTypeException $e) {
        return $this->result(422, [
          'status' => 'invalid_yaml',
          'valid' => FALSE,
          'message' => 'Invalid YAML: ' . $e->getMessage(),
          'errors' => [$e->getMessage()],
        ] + $body);
      }
    }
    else {
      $data = json_decode($raw, TRUE);
      if (json_last_error() !== JSON_ERROR_NONE) {
        return $this->result(422, [
          'status' => 'invalid_json',
          'valid' => FALSE,
          'message' => 'Invalid JSON: ' . json_last_error_msg(),
          'errors' => [json_last_error_msg()],
        ] + $body);
      }
    }

    if (!is_array($data)) {
      return $this->result(422, [
        'status' => 'not_array',
        'valid' => FALSE,
        'message' => 'The decoded payload is not an array/object of target entities.',
      ] + $body);
    }

    if ($count_errors = $this->helpers->validateGenerationCounts($data)) {
      return $this->result(422, [
        'status' => 'count_exceeded',
        'valid' => FALSE,
        'message' => implode(' ', $count_errors),
        'errors' => $count_errors,
      ] + $body);
    }

    $body['valid'] = TRUE;

    if ($validate_only) {
      return $this->result(200, [
        'status' => 'validated',
        'message' => 'The payload is syntactically valid. No entities were generated (validate_only).',
      ] + $body);
    }

    if (empty($this->configFactory->get('generator_engine.settings')->get('content_entities'))) {
      return $this->result(400, [
        'status' => 'no_target_entities',
        'message' => 'No target entities are configured. Select target entities on the configuration page first.',
      ] + $body);
    }

    $result = $this->helpers->generateFromArray($data);
    $generated = generator_engine_group_result_items($result);

    return $this->result(200, [
      'status' => 'success',
      'message' => sprintf('Generation finished. %d item(s) generated.', $generated['count']),
      'generated' => $generated,
    ] + $body);
  }

  /**
   * Wraps a body array with its HTTP status code.
   */
  protected function result($http_code, array $body) {
    return ['http_code' => $http_code, 'body' => $body];
  }

}
