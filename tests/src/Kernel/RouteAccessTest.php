<?php

namespace Drupal\Tests\generator_engine\Kernel;

/**
 * Tests the access requirements on the module's routes.
 *
 * These assertions exist to stop three regressions: reintroducing a
 * hard-coded "_role" requirement, falling back to a borrowed core permission
 * instead of the module's own restricted one, and dropping the CSRF header
 * requirement from the state-changing API routes.
 *
 * @group generator_engine
 */
class RouteAccessTest extends GeneratorEngineKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['basic_auth', 'generator_engine_api'];

  /**
   * The module's own permission.
   */
  const PERMISSION = 'administer generator engine';

  /**
   * Tests that the module declares its permission as restricted.
   */
  public function testPermissionIsDeclaredRestricted() {
    $permissions = \Drupal::service('user.permissions')->getPermissions();

    $this->assertArrayHasKey(self::PERMISSION, $permissions);
    $this->assertNotEmpty(
      $permissions[self::PERMISSION]['restrict access'] ?? FALSE,
      'The permission is marked as restricted, because it allows creating content and user accounts.'
    );
  }

  /**
   * Tests that the admin routes are permission-based and carry no role check.
   */
  public function testAdminRoutesUsePermissionNotRole() {
    $route_provider = \Drupal::service('router.route_provider');
    $expected = [
      'generator_engine.entities' => '/admin/generate/entities',
      'generator_engine.config_form' => '/admin/generate/config',
      'generator_engine.config_content_form' => '/admin/generate/config/forms',
      'generator_engine.json_highlighter_form' => '/admin/generate/json/highlighter',
      'generator_engine.yaml_highlighter_form' => '/admin/generate/yaml/highlighter',
    ];

    foreach ($expected as $name => $path) {
      $route = $route_provider->getRouteByName($name);
      $this->assertSame($path, $route->getPath());
      $this->assertSame(self::PERMISSION, $route->getRequirement('_permission'));
      $this->assertNull($route->getRequirement('_role'), "$name must not gate access on a role.");
      $this->assertTrue($route->getOption('_admin_route'), "$name should use the admin theme.");
    }
  }

  /**
   * Tests that the API routes require a CSRF token and the permission.
   */
  public function testApiRoutesRequireCsrfToken() {
    $route_provider = \Drupal::service('router.route_provider');
    $expected = [
      'generator_engine_api.generate_json' => '/api/generator-engine/json',
      'generator_engine_api.generate_yaml' => '/api/generator-engine/yaml',
      'generator_engine_api.generate_file' => '/api/generator-engine/file',
    ];

    foreach ($expected as $name => $path) {
      $route = $route_provider->getRouteByName($name);
      $this->assertSame($path, $route->getPath());
      $this->assertSame(['POST'], $route->getMethods());
      $this->assertSame(self::PERMISSION, $route->getRequirement('_permission'));
      $this->assertNull($route->getRequirement('_role'), "$name must not gate access on a role.");

      // Cookie auth is accepted, so a token is mandatory. Core only enforces
      // it for requests carrying a session, leaving basic_auth unaffected.
      $this->assertContains('cookie', $route->getOption('_auth'));
      $this->assertSame('TRUE', $route->getRequirement('_csrf_request_header_token'));
    }
  }

}
