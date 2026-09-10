<?php

namespace Drupal\Tests\generator_engine_api\Kernel;

use Drupal\Tests\generator_engine\Kernel\GeneratorEngineKernelTestBase;

/**
 * Tests the shared back end behind the generation HTTP endpoints.
 *
 * GenerateApiService serves both the plain Basic-Auth routes and the REST
 * resources.
 *
 * @group generator_engine
 * @coversDefaultClass \Drupal\generator_engine_api\GenerateApiService
 */
class GenerateApiServiceTest extends GeneratorEngineKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'field', 'text', 'filter', 'node', 'taxonomy', 'image', 'file',
    'generator_engine', 'basic_auth', 'generator_engine_api',
  ];

  /**
   * Returns the generation API service.
   *
   * @return \Drupal\generator_engine_api\GenerateApiService
   *   The generation API service.
   */
  protected function api() {
    return \Drupal::service('generator_engine_api.processor');
  }

  /**
   * Turns the APIs on (they ship disabled).
   */
  protected function enableApis() {
    $this->config('generator_engine.settings')->set('enable_apis', TRUE)->save();
  }

  /**
   * A minimal valid JSON payload that creates one article.
   */
  protected function jsonPayload() {
    return '[{"node_type":{"article":{"check":1,"count":"1","fields":{"title":"API Article"}}}}]';
  }

  /**
   * The same payload expressed as YAML.
   */
  protected function yamlPayload() {
    return <<<YAML
- node_type:
    article:
      check: 1
      count: "1"
      fields:
        title: API Article
YAML;
  }

  /**
   * @covers ::isEnabled
   * @covers ::process
   */
  public function testDisabledByDefault() {
    $this->assertFalse($this->api()->isEnabled());
    $out = $this->api()->process($this->jsonPayload(), 'json');
    $this->assertSame(403, $out['http_code']);
    $this->assertSame('disabled', $out['body']['status']);
  }

  /**
   * @covers ::process
   */
  public function testEmptyPayload() {
    $this->enableApis();
    $out = $this->api()->process("   ", 'json');
    $this->assertSame(400, $out['http_code']);
    $this->assertSame('empty_payload', $out['body']['status']);
  }

  /**
   * @covers ::process
   */
  public function testInvalidJson() {
    $this->enableApis();
    $out = $this->api()->process('{bad json', 'json');
    $this->assertSame(422, $out['http_code']);
    $this->assertSame('invalid_json', $out['body']['status']);
    $this->assertFalse($out['body']['valid']);
    $this->assertNotEmpty($out['body']['errors']);
  }

  /**
   * @covers ::process
   */
  public function testInvalidYaml() {
    $this->enableApis();
    // Bad indentation - a mapping value under a sequence item that doesn't line
    // up - makes the YAML parser throw.
    $out = $this->api()->process("a:\n  - b\n - c\n", 'yaml');
    $this->assertSame(422, $out['http_code']);
    $this->assertSame('invalid_yaml', $out['body']['status']);
    $this->assertFalse($out['body']['valid']);
  }

  /**
   * @covers ::process
   */
  public function testValidateOnlyGeneratesNothing() {
    $this->enableApis();
    $out = $this->api()->process($this->jsonPayload(), 'json', TRUE);
    $this->assertSame(200, $out['http_code']);
    $this->assertSame('validated', $out['body']['status']);
    $this->assertTrue($out['body']['valid']);
    $this->assertNull($out['body']['generated']);

    $articles = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['type' => 'article']);
    $this->assertCount(0, $articles, 'validate_only must not create entities.');
  }

  /**
   * @covers ::process
   */
  public function testJsonGenerates() {
    $this->enableApis();
    $out = $this->api()->process($this->jsonPayload(), 'json');
    $this->assertSame(200, $out['http_code']);
    $this->assertSame('success', $out['body']['status']);
    $this->assertSame(1, $out['body']['generated']['count']);
    // Items are grouped by the base entity type ("node"), then bundle.
    $this->assertSame(1, $out['body']['generated']['items_by_type']['node']['article']);

    $articles = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['type' => 'article']);
    $this->assertCount(1, $articles);
    $this->assertSame('API Article', reset($articles)->getTitle());
  }

  /**
   * @covers ::process
   */
  public function testYamlGenerates() {
    $this->enableApis();
    $out = $this->api()->process($this->yamlPayload(), 'yaml');
    $this->assertSame(200, $out['http_code']);
    $this->assertSame('success', $out['body']['status']);
    $this->assertSame(1, $out['body']['generated']['count']);

    $articles = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['type' => 'article']);
    $this->assertCount(1, $articles);
  }

}
