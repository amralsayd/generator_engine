<?php

namespace Drupal\generator_engine_api_rest\Plugin\rest\resource;

use Drupal\generator_engine_api\GenerateApiService;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shared base for the Generator Engine REST resources.
 *
 * All three resources (json, yaml, file) accept the same JSON envelope and
 * hand it to GenerateApiService::process() - the same back end the plain
 * Basic-Auth routes in GenerateApiController use. Authorization is pinned to
 * the "administer generator engine" permission (see getBaseRouteRequirements())
 * instead of the auto-generated "restful post <id>" permission.
 */
abstract class GenerateRestResourceBase extends ResourceBase {

  /**
   * The generation API service.
   *
   * @var \Drupal\generator_engine_api\GenerateApiService
   */
  protected $api;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, GenerateApiService $api) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->api = $api;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('generator_engine_api.processor')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function permissions() {
    // Don't register a per-resource "restful post <id>" permission; access is
    // governed by the "administer generator engine" requirement added below.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function getBaseRouteRequirements($method) {
    $requirements = parent::getBaseRouteRequirements($method);
    $requirements['_permission'] = 'administer generator engine';
    return $requirements;
  }

  /**
   * Validates the envelope and runs the generation pipeline.
   *
   * @param mixed $data
   *   The deserialized request body. Expected shape:
   *   {"payload": "<json or yaml text>", "validate_only": false}.
   * @param string $format
   *   Either 'json' or 'yaml'.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The response, never cached.
   */
  protected function handlePost($data, $format) {
    if (!is_array($data) || !isset($data['payload']) || !is_string($data['payload'])) {
      return new ModifiedResourceResponse([
        'status' => 'missing_payload',
        'valid' => NULL,
        'format' => $format,
        'validate_only' => is_array($data) ? !empty($data['validate_only']) : FALSE,
        'message' => 'The request body must be a JSON object with a string "payload" field.',
        'errors' => [],
        'generated' => NULL,
      ], 422);
    }

    $out = $this->api->process($data['payload'], $format, !empty($data['validate_only']));
    return new ModifiedResourceResponse($out['body'], $out['http_code']);
  }

}
