<?php

namespace Drupal\Tests\generator_engine_api_rest\Kernel;

use Drupal\rest\Entity\RestResourceConfig;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\Tests\generator_engine\Kernel\GeneratorEngineKernelTestBase;

/**
 * Tests the generator_engine_api_rest REST resources.
 *
 * Covers that their config installs, that their routes register with the
 * right access, and that a POST through the plugin runs the shared
 * generation pipeline.
 *
 * @group generator_engine
 */
class RestResourcesTest extends GeneratorEngineKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'field', 'text', 'filter', 'node', 'taxonomy', 'image', 'file',
    'generator_engine', 'serialization', 'basic_auth', 'rest',
    'generator_engine_api', 'generator_engine_api_rest',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Import this module's rest.resource.* config (KernelTestBase only enables
    // modules, it doesn't run their config/install).
    $this->installConfig(['generator_engine_api_rest']);
  }

  /**
   * The three resource config entities install with basic_auth + cookie auth.
   */
  public function testResourceConfigInstalled() {
    foreach (['generator_engine_json', 'generator_engine_yaml', 'generator_engine_file'] as $id) {
      $config = RestResourceConfig::load($id);
      $this->assertNotNull($config, "rest.resource.$id is installed.");
      $this->assertSame(['POST'], $config->getMethods());
      $auth = $config->getAuthenticationProviders('POST');
      $this->assertContains('basic_auth', $auth);
      $this->assertContains('cookie', $auth);
    }
  }

  /**
   * Tests that the POST routes register with the expected access.
   *
   * They must be pinned to "administer generator engine" and must not carry
   * an auto-generated "restful post <id>" permission.
   */
  public function testRoutesRegistered() {
    $route_provider = \Drupal::service('router.route_provider');
    $expected = [
      'rest.generator_engine_json.POST' => '/api/generator-engine/rest/json',
      'rest.generator_engine_yaml.POST' => '/api/generator-engine/rest/yaml',
      'rest.generator_engine_file.POST' => '/api/generator-engine/rest/file',
    ];
    foreach ($expected as $name => $path) {
      $route = $route_provider->getRouteByName($name);
      $this->assertSame($path, $route->getPath());
      $this->assertSame('administer generator engine', $route->getRequirement('_permission'));
    }

    $permissions = \Drupal::service('user.permissions')->getPermissions();
    $this->assertArrayNotHasKey('restful post generator_engine_json', $permissions);
  }

  /**
   * A POST through the JSON resource plugin runs the generation pipeline.
   */
  public function testJsonResourcePost() {
    $this->config('generator_engine.settings')->set('enable_apis', TRUE)->save();

    $plugin = \Drupal::service('plugin.manager.rest')->createInstance('generator_engine_json');
    $response = $plugin->post([
      'payload' => '[{"node_type":{"article":{"check":1,"count":"1","fields":{"title":"rest kt"}}}}]',
    ]);

    $this->assertInstanceOf(ModifiedResourceResponse::class, $response);
    $body = $response->getResponseData();
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('success', $body['status']);
    $this->assertSame(1, $body['generated']['count']);

    $articles = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['type' => 'article']);
    $this->assertCount(1, $articles);
    $this->assertSame('rest kt', reset($articles)->getTitle());
  }

  /**
   * A body without a string "payload" is rejected before any generation.
   */
  public function testMissingPayload() {
    $this->config('generator_engine.settings')->set('enable_apis', TRUE)->save();

    $plugin = \Drupal::service('plugin.manager.rest')->createInstance('generator_engine_json');
    $response = $plugin->post(['foo' => 1]);

    $this->assertSame(422, $response->getStatusCode());
    $this->assertSame('missing_payload', $response->getResponseData()['status']);

    $articles = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['type' => 'article']);
    $this->assertCount(0, $articles);
  }

}
