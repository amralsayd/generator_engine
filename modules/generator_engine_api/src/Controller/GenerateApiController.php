<?php

namespace Drupal\generator_engine_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\generator_engine_api\GenerateApiService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Basic-Auth HTTP endpoints for the JSON/YAML/file generation APIs.
 *
 * These are the plain-route flavour; the equivalent @RestResource plugins live
 * in the generator_engine_api_rest sub-module. Both funnel into
 * GenerateApiService::process().
 */
class GenerateApiController extends ControllerBase {

  /**
   * The generation API service.
   *
   * @var \Drupal\generator_engine_api\GenerateApiService
   */
  protected $api;

  /**
   * Constructs a GenerateApiController.
   */
  public function __construct(GenerateApiService $api) {
    $this->api = $api;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('generator_engine_api.processor'));
  }

  /**
   * POST /api/generator-engine/json - raw JSON body.
   */
  public function json(Request $request) {
    return $this->respond($request, $request->getContent(), 'json');
  }

  /**
   * POST /api/generator-engine/yaml - raw YAML body.
   */
  public function yaml(Request $request) {
    return $this->respond($request, $request->getContent(), 'yaml');
  }

  /**
   * POST /api/generator-engine/file - multipart upload or raw body.
   *
   * The format is auto-detected from the uploaded file's extension
   * (yml/yaml => yaml, otherwise json) and can be overridden with ?format=.
   */
  public function file(Request $request) {
    /** @var \Symfony\Component\HttpFoundation\File\UploadedFile|null $upload */
    $upload = $request->files->get('file');
    $format_override = $request->query->get('format');

    if ($upload !== NULL) {
      if (!$upload->isValid()) {
        return new JsonResponse([
          'status' => 'no_file',
          'valid' => NULL,
          'format' => NULL,
          'validate_only' => $request->query->getBoolean('validate_only'),
          'message' => 'The uploaded file could not be read: ' . $upload->getErrorMessage(),
          'errors' => [$upload->getErrorMessage()],
          'generated' => NULL,
        ], 400);
      }
      $raw = file_get_contents($upload->getPathname());
      $format = $format_override ?: $this->detectFormat($upload->getClientOriginalName());
    }
    else {
      $raw = $request->getContent();
      if (!is_string($raw) || trim($raw) === '') {
        return new JsonResponse([
          'status' => 'no_file',
          'valid' => NULL,
          'format' => NULL,
          'validate_only' => $request->query->getBoolean('validate_only'),
          'message' => 'No "file" upload and no request body were provided.',
          'errors' => [],
          'generated' => NULL,
        ], 400);
      }
      $format = $format_override ?: $this->detectFormat($request->headers->get('Content-Disposition', ''));
    }

    return $this->respond($request, $raw, $format);
  }

  /**
   * Runs GenerateApiService::process() and returns its JSON response.
   */
  protected function respond(Request $request, $raw, $format) {
    $out = $this->api->process($raw, $format, $request->query->getBoolean('validate_only'));
    return new JsonResponse($out['body'], $out['http_code']);
  }

  /**
   * Maps a filename (or any string ending in an extension) to a format.
   *
   * Mirrors GenerationCommands::detectFormat() in the parent module.
   */
  protected function detectFormat($name) {
    $ext = strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));
    return ($ext === 'yml' || $ext === 'yaml') ? 'yaml' : 'json';
  }

}
