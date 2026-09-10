<?php

namespace Drupal\generator_engine;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\generator_engine\Classes\GeneratorEngine;
use Drupal\generator_engine\Classes\GeneratorEngineBatch;
use Drupal\generator_engine\Procedures\UsersTagsContentProcedures;
use Drupal\generator_engine\Util\Text\Lorem;

/**
 * Builds generation configuration and dispatches runs to the engines.
 */
class HelpersService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a HelpersService.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
    ConfigFactoryInterface $config_factory,
    ModuleHandlerInterface $module_handler
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->configFactory = $config_factory;
    $this->moduleHandler = $module_handler;
  }

  /**
   * Generates entities directly, without the Batch API.
   *
   * @param array $target_entities
   *   The generation statement.
   * @param int|string|null $batch
   *   Existing batch node id to append to.
   * @param bool $exclude
   *   Whether to filter against the configured entity/bundle allow-list.
   *
   * @return array
   *   ['batch_id' => mixed, 'items' => array] for the run.
   */
  public function generateEngine($target_entities, $batch = NULL, $exclude = TRUE) {

    $engine = new GeneratorEngine();
    $result = $engine->execute($target_entities, $this->getEntitiesConfig($exclude), $batch);

    return $result;
  }

  /**
   * Generates numerically-keyed parts in order, threading references.
   *
   * @param array $target_entities
   *   Ordered generation statements.
   * @param int|string|null $batch
   *   Existing batch node id to append to.
   * @param bool $exclude
   *   Whether to filter against the configured entity/bundle allow-list.
   *
   * @return array
   *   ['batch_id' => mixed, 'items' => array] for the run.
   */
  public function generateEngineDependant($target_entities, $batch = NULL, $exclude = TRUE) {

    $engine = new GeneratorEngine();
    $result = $engine->executeDependant($target_entities, $this->getEntitiesConfig($exclude), $batch);

    return $result;
  }

  /**
   * Generates entities through the Batch API.
   *
   * @param array $target_entities
   *   The generation statement.
   * @param int|string|null $batch
   *   Existing batch node id to append to.
   * @param bool $exclude
   *   Whether to filter against the configured entity/bundle allow-list.
   *
   * @return array
   *   Batch operations for the run.
   */
  public function generateEngineBatch($target_entities, $batch = NULL, $exclude = TRUE) {
    $engine = new GeneratorEngineBatch();
    return $engine->execute($target_entities, $this->getEntitiesConfig($exclude), $batch);
  }

  /**
   * Dispatches decoded target-entities data to the right engine call.
   *
   * If $target_entities contains keys like "0", "1", "2", ... it is treated
   * as multiple dependant parts and routed to generateEngineDependant(),
   * otherwise it is routed to generateEngine(). Shared by the JSON/YAML
   * highlighter forms and the generator_engine:generate Drush command.
   */
  public function generateFromArray($target_entities, $batch = NULL, $exclude = TRUE) {
    if (!is_array($target_entities)) {
      return [];
    }

    // Guard the entry point every caller funnels through, so an oversized
    // request cannot reach the engine from Drush or a direct service call
    // even when the calling layer forgot to validate.
    if ($errors = $this->validateGenerationCounts($target_entities)) {
      throw new \InvalidArgumentException(implode(' ', $errors));
    }

    if (!empty(array_filter(array_keys($target_entities), 'is_int'))) {
      return $this->generateEngineDependant($target_entities, $batch, $exclude);
    }

    return $this->generateEngine($target_entities, $batch, $exclude);
  }

  /**
   * Returns the largest count a single generation statement may request.
   *
   * @return int
   *   The configured maximum, defaulting to 500.
   */
  public function maxGenerationCount() {
    $max = $this->configFactory->get('generator_engine.settings')->get('max_generation_count');
    return $max > 0 ? (int) $max : 500;
  }

  /**
   * Checks every count in a target-entities structure against the maximum.
   *
   * Generation is synchronous, so an unbounded count ties up the request until
   * it times out. The structure is walked recursively because the dependant
   * format nests statements under numeric keys.
   *
   * @param mixed $target_entities
   *   A decoded target-entities structure.
   *
   * @return string[]
   *   Violation messages, empty when the structure is within bounds.
   */
  public function validateGenerationCounts($target_entities) {
    $max = $this->maxGenerationCount();
    $errors = [];

    $walk = function ($branch) use (&$walk, $max, &$errors) {
      if (!is_array($branch)) {
        return;
      }
      foreach ($branch as $key => $value) {
        if ($key === 'count' && is_scalar($value) && (int) $value > $max) {
          $errors[] = sprintf('A generation count of %d exceeds the maximum of %d.', (int) $value, $max);
        }
        elseif (is_array($value)) {
          $walk($value);
        }
      }
    };
    $walk($target_entities);

    return $errors;
  }

  /**
   * Generates lorem ipsum body text.
   *
   * @param int $lines
   *   Number of paragraphs to generate.
   *
   * @return string
   *   The generated text.
   */
  public function generateTextLorem($lines = 1) {
    return Lorem::ipsum(max(1, (int) $lines));
  }

  /**
   * Runs the users -> tags -> authored/tagged articles & pages procedure.
   *
   * @see \Drupal\generator_engine\Procedures\UsersTagsContentProcedures
   */
  public function runUsersTagsContentProcedure($params, $options) {
    $procedure = new UsersTagsContentProcedures($params, $options, $this);
    return $procedure->run();
  }

  /**
   * Lists the entity types this module can generate, with their bundles.
   *
   * @return array
   *   Entity type name keyed to its class, properties and bundles.
   */
  public function getEntitiesConfigFieldsMain() {
    $target_entities_types = [
      'node_type' => [
        'class' => '\Drupal\node\Entity\Node',
        'type_property' => 'type',
        'title_property' => 'title',
        // 'class_nodes' => 'EntityCustomNodesGenerator',
        // 'class_single_node' => 'EntityCustomSingleNodeGenerator'
      ],
      'vote_type' => [
        'class' => '\Drupal\votingapi\Entity\Vote',
        'type_property' => 'type',
        'title_property' => 'title',
      ],
      'flag' => [
        'class' => '\Drupal\flag\Entity\Flag',
        'type_property' => 'type',
        'title_property' => 'title',
      ],
      'paragraphs_type' => [
        'class' =>
        '\Drupal\paragraphs\Entity\Paragraph',
        'type_property' => 'type',
        'title_property' => 'title',
      ],
      'taxonomy_vocabulary' => [
        'class' => '\Drupal\taxonomy\Entity\Term',
        'type_property' => 'vid',
        'title_property' => 'name',
      ],
      'media_type' => [
        'class' => '\Drupal\media\Entity\Media',
        'type_property' => 'bundle',
        'title_property' => 'title',
      ],
      'comment_type' => [
        'class' => '\Drupal\comment\Entity\Comment',
        'type_property' => 'comment_type',
        'title_property' => 'title',
      ],
      // ECK , entity constractor kit
    ];

    $entities_config = [];
    $entities_types = [];

    $system_entities = $this->entityTypeManager->getDefinitions();

    foreach ($system_entities as $entity_name => $system_entity) {
      if (in_array($entity_name, array_keys($target_entities_types))) {
        $entities_types[$entity_name]['bundle_of'] = $system_entity->getBundleOf();
        $entities_types[$entity_name]['class'] = $target_entities_types[$entity_name]['class'];
        $entities_types[$entity_name]['type_property'] = $target_entities_types[$entity_name]['type_property'];
        $entities_types[$entity_name]['title_property'] = $target_entities_types[$entity_name]['title_property'];
        $entities_types[$entity_name]['class_nodes'] = $target_entities_types[$entity_name]['class_nodes'] ?? '';
        $entities_types[$entity_name]['class_single_node'] = $target_entities_types[$entity_name]['class_single_node'] ?? '';
        $entities_types[$entity_name]['bundles'] = $this->entityTypeManager
          ->getStorage($entity_name)
          ->loadMultiple();
      }
    }

    // Load user entity type (because the user is special entity type)
    $entities_types['user']['bundle_of'] = 'user';
    $entities_types['user']['bundles']['user'] = 'user';
    $entities_types['user']['class'] = '\Drupal\user\Entity\User';
    $entities_types['user']['type_property'] = 'type';
    $entities_types['user']['title_property'] = 'name';
    $entities_types['user']['class_nodes'] = 'EntityUsersGenerator';
    $entities_types['user']['class_single_node'] = 'EntityUserSingleGenerator';

    foreach ($entities_types as $content_type_bundle => $entity_type) {
      foreach ($entity_type['bundles'] as $entity_bundle_name => $entity_bundle) {
        $entities_config[$content_type_bundle][$entity_bundle_name]['type'] = $entity_type['bundle_of'];
        $entities_config[$content_type_bundle][$entity_bundle_name]['entity_class'] = $entity_type['class'];
        $entities_config[$content_type_bundle][$entity_bundle_name]['entity_type_property'] = $entity_type['type_property'];
        $entities_config[$content_type_bundle][$entity_bundle_name]['entity_title_property'] = $entity_type['title_property'];
        $entities_config[$content_type_bundle][$entity_bundle_name]['classNodes'] = $entity_type['class_nodes'] ?? '';
        $entities_config[$content_type_bundle][$entity_bundle_name]['classSingleNode'] = $entity_type['class_single_node'] ?? '';
      }
    }

    return $entities_config;
  }

  /**
   * Builds per-bundle field configuration for the generation forms.
   *
   * @param bool $exclude
   *   Whether to filter against the configured entity/bundle allow-list.
   *
   * @return array
   *   Bundle configuration keyed by entity type.
   */
  public function getEntitiesConfigFields($exclude = TRUE) {
    $main_entities = $this->getEntitiesConfigFieldsMain();
    $main_entities_config = $this->configFactory->get('generator_engine.settings');

    $entities_config = [];

    if (empty($main_entities_config->get('content_entities'))) {
      return [];
    }

    foreach ($main_entities as $content_type_bundle => $entity_type) {
      if ($exclude && (!in_array($content_type_bundle, array_keys($main_entities_config->get('content_entities'))) || $main_entities_config->get('content_entities')[$content_type_bundle]['check'] == 0)) {
        continue;
      }

      $bundle_config = $main_entities_config->get('content_entities.' . $content_type_bundle . '.bundles.items') ?? [];
      $config_bundles = array_values($bundle_config);
      foreach ($entity_type as $entity_bundle_name => $entity_bundle) {
        if ($exclude && !in_array($entity_bundle_name, $config_bundles, TRUE)) {
          continue;
        }

        $entities_config[$content_type_bundle][$entity_bundle_name] = $entity_bundle;
        $default_content_fields = $this->entityFieldManager->getFieldDefinitions($entity_bundle['type'], $entity_bundle_name);

        foreach ($default_content_fields as $default_content_field_name => $default_content_field_config) {
          // Handle special proprties.
          if ($default_content_field_name == 'status') {
            $entities_config[$content_type_bundle][$entity_bundle_name]['properties']['status'] = [
              'property' => 'custom',
              'ref' => 'status',
            ];
          }

          if ($default_content_field_name == 'uid') {
            $entities_config[$content_type_bundle][$entity_bundle_name]['properties']['uid'] = [
              'property' => 'custom',
              'ref' => 'user',
            ];
          }

          if ($default_content_field_name == 'roles') {
            $entities_config[$content_type_bundle][$entity_bundle_name]['properties']['roles'] = [
              'property' => 'custom',
              'ref' => 'user_roles',
            ];
          }

          if ($entity_bundle_name == 'user') {
            if ($default_content_field_name == 'name') {
              $entities_config[$content_type_bundle][$entity_bundle_name]['properties']['name'] = [
                'property' => 'custom',
                'ref' => 'name',
              ];

            }

            if ($default_content_field_name == 'status') {
              $entities_config[$content_type_bundle][$entity_bundle_name]['properties']['status'] = [
                'property' => 'custom',
                'ref' => 'user_status',
              ];
            }
          }

          // "name" is special: the setter differs depending on the entity
          // it is attached to (user, term and so on). Special attributes
          // such as created, changed or vid are handled the same way.
          // @todo Handle the "entity_reference_revisions" type for paragraphs.
          $named_fields = [
            'title',
            'body',
            'created',
            'changed',
            'name',
            'user_picture',
          ];
          if (str_contains($default_content_field_name, 'field_') || in_array($default_content_field_name, $named_fields)) {
            $default_content_field_settings = $default_content_field_config->getSettings();

            if (in_array($default_content_field_config->getType(), ['entity_reference'])) {
              $entities_config[$content_type_bundle][$entity_bundle_name]['custom_fields'][$default_content_field_name] = [
                'mode' => 'default',
                'bind' => 'ef',
              ];
            }
            $default_content_field_settings = $default_content_field_config->getSettings();

            if (in_array($default_content_field_config->getType(), ['file', 'image'])) {
              $entities_config[$content_type_bundle][$entity_bundle_name]['custom_fields'][$default_content_field_name] = [
                'mode' => 'default',
                'bind' => 'file',
              ];
            }

            if (in_array($default_content_field_config->getType(), ['image', 'user_picture'])) {
              $entities_config[$content_type_bundle][$entity_bundle_name]['custom_fields'][$default_content_field_name] = [
                'mode' => 'default',
                'bind' => 'image',
              ];
            }

            if (in_array($default_content_field_config->getType(), ['date'])) {
              $entities_config[$content_type_bundle][$entity_bundle_name]['custom_fields'][$default_content_field_name] = [
                'mode' => 'default',
                'bind' => 'date',
              ];
            }

            $value_field_types = [
              'text_long',
              'list_string',
              'link',
              'boolean',
              'string',
              'datetime',
              'string_long',
              'integer',
              'decimal',
              'phone_international',
            ];
            if (in_array($default_content_field_config->getType(), $value_field_types)) {
              $entities_config[$content_type_bundle][$entity_bundle_name]['custom_fields'][$default_content_field_name] = [
                'mode' => 'default',
                'bind' => 'value',
              ];
            }

            if (in_array($default_content_field_config->getType(), ['link'])) {
              $entities_config[$content_type_bundle][$entity_bundle_name]['custom_fields'][$default_content_field_name] = [
                'mode' => 'default',
                'bind' => 'uri',
              ];
            }

            if (@$default_content_field_settings['target_type'] == 'media') {
              $target_bundles = $default_content_field_settings['handler_settings']['target_bundles'];
              // @todo detect media type from field configuration
              $entities_config[$content_type_bundle][$entity_bundle_name]['custom_fields'][$default_content_field_name] = [
                'mode' => 'default',
                'bind' => 'media',
                'extra' => $target_bundles,
              ];
            }

            if (in_array($default_content_field_name, ['body', 'created', 'changed'])) {
              $entities_config[$content_type_bundle][$entity_bundle_name]['custom_fields'][$default_content_field_name] = [
                'mode' => 'default',
                'bind' => 'value',
              ];
            }

            if ($entity_bundle_name != 'user' && in_array($default_content_field_name, ['name'])) {
              $entities_config[$content_type_bundle][$entity_bundle_name]['custom_fields'][$default_content_field_name] = [
                'mode' => 'default',
                'bind' => 'value',
              ];
            }

            // @todo handle type 'path', 'uri'
          }
        }

        if (empty($entities_config[$content_type_bundle][$entity_bundle_name]['properties'])) {
          $entities_config[$content_type_bundle][$entity_bundle_name]['properties'] = [];
        }

      }
    }
    return $entities_config;
  }

  /**
   * Returns the generation configuration after letting modules alter it.
   *
   * @param bool $exclude
   *   Whether to filter against the configured entity/bundle allow-list.
   *
   * @return array
   *   Bundle configuration keyed by entity type.
   *
   * @see hook_generator_engine_entities_config_alter()
   */
  public function getEntitiesConfig($exclude = TRUE) {
    $configs = $this->getEntitiesConfigFields($exclude);
    // Allow other modules to add or override bundle/field definitions,
    // e.g. to register a site-specific content model.
    // @see hook_generator_engine_entities_config_alter()
    $this->moduleHandler->alter('generator_engine_entities_config', $configs);
    return $configs;
  }

}
