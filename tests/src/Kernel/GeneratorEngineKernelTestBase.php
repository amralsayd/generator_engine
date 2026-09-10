<?php

namespace Drupal\Tests\generator_engine\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\User;

/**
 * Base class for generator_engine Kernel tests.
 *
 * Builds a minimal, self-contained schema (article/page node types, a
 * "tags" vocabulary, field_tags/field_image/user_picture fields) so tests
 * don't depend on the real site's config/sync content model, and installs
 * the module's own default generator_engine.settings config so the
 * exclude-filtered code paths (used by the real forms/CLI command) work
 * without any hand-rolled configuration.
 */
abstract class GeneratorEngineKernelTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'node',
    'taxonomy',
    'image',
    'file',
    'generator_engine',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('file');
    // file_usage is a plain DB table from file.install's hook_schema(), not
    // part of the File entity's own schema installed above.
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['field', 'node', 'user']);

    // Imports the module's own config/install/generator_engine.settings.yml,
    // which already allow-lists node_type (article, page), taxonomy_vocabulary
    // (tags) and user (user) - exactly the bundles these tests use.
    $this->installConfig(['generator_engine']);

    foreach (['article', 'page'] as $bundle) {
      NodeType::create(['type' => $bundle, 'name' => ucfirst($bundle)])->save();
      \node_add_body_field(NodeType::load($bundle));
    }

    Vocabulary::create(['vid' => 'tags', 'name' => 'Tags'])->save();

    $this->createReferenceField('field_tags', 'node', ['article', 'page'], 'taxonomy_term', ['tags' => 'tags']);
    $this->createImageField('field_image', 'node', ['article', 'page']);
    $this->createImageField('user_picture', 'user', ['user']);
  }

  /**
   * Creates an entity_reference field storage + instances.
   */
  protected function createReferenceField($field_name, $entity_type, array $bundles, $target_type, array $target_bundles = []) {
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'type' => 'entity_reference',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
      'settings' => ['target_type' => $target_type],
    ])->save();

    foreach ($bundles as $bundle) {
      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => $entity_type,
        'bundle' => $bundle,
        'settings' => [
          'handler' => 'default:' . $target_type,
          'handler_settings' => ['target_bundles' => $target_bundles],
        ],
      ])->save();
    }
  }

  /**
   * Creates an image field storage + instances.
   */
  protected function createImageField($field_name, $entity_type, array $bundles) {
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'type' => 'image',
      'settings' => ['target_type' => 'file'],
    ])->save();

    foreach ($bundles as $bundle) {
      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => $entity_type,
        'bundle' => $bundle,
      ])->save();
    }
  }

  /**
   * Creates a file field storage + instances.
   */
  protected function createFileField($field_name, $entity_type, array $bundles) {
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'type' => 'file',
      'settings' => ['target_type' => 'file'],
    ])->save();

    foreach ($bundles as $bundle) {
      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => $entity_type,
        'bundle' => $bundle,
      ])->save();
    }
  }

  /**
   * Creates a field storage and instances for a simple field type.
   *
   * Suitable for types needing no special settings (string, boolean, link).
   */
  protected function createSimpleField($field_name, $entity_type, array $bundles, $type) {
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'type' => $type,
    ])->save();

    foreach ($bundles as $bundle) {
      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => $entity_type,
        'bundle' => $bundle,
      ])->save();
    }
  }

  /**
   * Creates the requested number of "tags" terms.
   *
   * @param int $count
   *   How many terms to create.
   *
   * @return int[]
   *   The created term ids.
   */
  protected function createTags($count) {
    $tids = [];
    for ($i = 0; $i < $count; $i++) {
      $term = Term::create(['vid' => 'tags', 'name' => 'Existing Tag ' . $i]);
      $term->save();
      $tids[] = (int) $term->id();
    }
    return $tids;
  }

  /**
   * Creates the requested number of users.
   *
   * @param int $count
   *   How many users to create.
   *
   * @return int[]
   *   The created user ids.
   */
  protected function createUsers($count) {
    $uids = [];
    for ($i = 0; $i < $count; $i++) {
      $user = User::create([
        'name' => 'existing_user_' . $i . '_' . $this->randomMachineName(4),
        'mail' => 'existing-user-' . $i . '@example.com',
        'status' => 1,
      ]);
      $user->save();
      $uids[] = (int) $user->id();
    }
    return $uids;
  }

  /**
   * Returns the generation helpers service.
   *
   * @return \Drupal\generator_engine\HelpersService
   *   The generation helpers service.
   */
  protected function generateHelpers() {
    return \Drupal::service('generator_engine.helpers');
  }

}
