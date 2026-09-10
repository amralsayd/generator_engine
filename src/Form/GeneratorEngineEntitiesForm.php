<?php

namespace Drupal\generator_engine\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Builds a per-bundle form for generating content entities.
 */
class GeneratorEngineEntitiesForm extends FormBase {

  /**
   * The generation helpers service.
   *
   * @var \Drupal\generator_engine\HelpersService
   */
  protected $helpers;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->helpers = $container->get('generator_engine.helpers');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->formBuilder = $container->get('form_builder');
    $instance->moduleHandler = $container->get('module_handler');
    $instance->setConfigFactory($container->get('config.factory'));
    $instance->setMessenger($container->get('messenger'));
    $instance->setLoggerFactory($container->get('logger.factory'));
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'generator_engine_entities_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $main_entities_config = $this->config('generator_engine.settings');
    if (empty($main_entities_config->get('content_entities'))) {
      $this->messenger()->addWarning($this->t('Select target entities on the configuration page before using the generation form.'));
      return [];
    }

    $entities_def = $this->helpers->getEntitiesConfig();
    $main_entities_config = $this->config('generator_engine.settings');

    if (empty($main_entities_config->get('form_entities'))) {
      return [];
    }

    $form['#tree'] = TRUE;
    $form['content_entities'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Generator Engine Entities'),
      '#prefix' => '<div id="learners-fieldset-wrapper">',
      '#suffix' => '</div>',
    ];

    $form['content_entities_desc'] = [
      '#theme' => 'item_list',
      '#title' => $this->t('Hints'),
      '#items' => [
        $this->t('For text fields such as the title, append "+" followed by your own text to add it to the generated string.'),
        $this->t('Set a batch to accumulate the generated entities under a single batch id.'),
      ],
    ];

    foreach ($entities_def as $entity_type => $entity_type_config) {
      if (!in_array($entity_type, array_keys($main_entities_config->get('form_entities'))) || $main_entities_config->get('form_entities')[$entity_type]['check'] == 0) {
        continue;
      }

      $bundle_config = $main_entities_config->get('form_entities.' . $entity_type . '.bundles.items') ?? [];
      $config_bundles = array_values($bundle_config);

      $form['content_entities'][$entity_type] = [
        '#type' => 'details',
        '#title' => $entity_type,
        '#open' => TRUE,
      ];

      foreach ($entity_type_config as $entity_def => $entity_config) {
        if (!in_array($entity_def, $config_bundles, TRUE)) {
          continue;
        }

        $entity_class = $entity_config['entity_class'];
        if (empty($entity_class)) {
          continue;
        }

        $form['content_entities'][$entity_type][$entity_def] = [
          '#type' => 'details',
          '#title' => $entity_def,
          '#open' => FALSE,
        ];
        $form['content_entities'][$entity_type][$entity_def]['check'] = [
          '#type' => 'checkbox',
          '#title' => $entity_def,
        ];
        $form['content_entities'][$entity_type][$entity_def]['count'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Number of entities'),
        ];

        if (!empty($entity_config['custom_fields'])) {
          $form_elements = [];
          foreach ($entity_config['custom_fields'] as $custom_field_key => $custom_field) {
            $entity = NULL;
            if (!empty($custom_field['mode'])) {
              $entity_class = $entity_config['entity_class'];
              $entity_type_property = $entity_config['entity_type_property'];

              $entity = $entity_class::create([$entity_type_property => $entity_def]);

              $form_build = NULL;
              $form_elements = [];

              if ($custom_field['mode'] != 'custom') {
                try {
                  $form_build = $this->entityTypeManager->getFormObject($entity_config['type'], $custom_field['mode'])->setEntity($entity);
                  $form_elements = $this->formBuilder->getForm($form_build);
                }
                catch (\Exception $e) {
                  // The bundle has no form display for this mode, so there is
                  // no widget to derive a generation element from. Skip the
                  // field rather than failing the whole form.
                  $this->logger('generator_engine')->debug('Skipped field @field on @bundle: no "@mode" form display. @message', [
                    '@field' => $custom_field_key,
                    '@bundle' => $entity_def,
                    '@mode' => $custom_field['mode'],
                    '@message' => $e->getMessage(),
                  ]);
                  continue;
                }

                if (@$form_elements[$custom_field_key]['widget']['#type'] == 'select' || @$form_elements[$custom_field_key]['widget']['#type'] == 'checkboxes' || @$form_elements[$custom_field_key]['widget']['#type'] == 'radios') {
                  $form['content_entities'][$entity_type][$entity_def]['fields'][$custom_field_key] = array_intersect_key(
                  // The array with all keys.
                    $form_elements[$custom_field_key]['widget'],
                  // Keys to be extracted.
                    array_flip(['#title', '#type', '#options', '#multiple'])
                  );
                }
                elseif (@!empty($form_elements[$custom_field_key]['widget']['value'])) {
                  $form['content_entities'][$entity_type][$entity_def]['fields'][$custom_field_key] = array_intersect_key(
                  // The array with all keys.
                    $form_elements[$custom_field_key]['widget']['value'],
                  // Keys to be extracted.
                    array_flip(['#title', '#type'])
                  );
                }
                elseif (@!empty($form_elements[$custom_field_key]['widget'][0]['value'])) {
                  $form['content_entities'][$entity_type][$entity_def]['fields'][$custom_field_key] = array_intersect_key(
                  // The array with all keys.
                    $form_elements[$custom_field_key]['widget'][0]['value'],
                  // Keys to be extracted.
                    array_flip(['#title', '#type'])
                  );
                }
                elseif (@!empty($form_elements[$custom_field_key]['widget'][0]['target_id'])) {
                  $form['content_entities'][$entity_type][$entity_def]['fields'][$custom_field_key] = array_intersect_key(
                  // The array with all keys.
                    $form_elements[$custom_field_key]['widget'][0]['target_id'],
                    array_flip([
                      '#title',
                      '#type',
                      '#target_type',
                      '#selection_settings',
                      '#selection_handler',
                      '#autocomplete_route_parameters',
                      '#autocomplete_route_name',
                  // Keys to be extracted.
                    ])
                  );

                }
                elseif (@$form_elements[$custom_field_key]['widget'][0]['#type'] == 'managed_file') {
                  $form['content_entities'][$entity_type][$entity_def]['fields'][$custom_field_key] = $this->fileField($form_elements[$custom_field_key]['widget'][0]['#title']);
                }
                elseif ($custom_field['bind'] == 'media') {
                  $form['content_entities'][$entity_type][$entity_def]['fields'][$custom_field_key] = $this->fileField($form_elements[$custom_field_key]['widget']['#title']);
                }
                else {
                  if (!empty($form_elements[$custom_field_key])) {
                    $form['content_entities'][$entity_type][$entity_def]['fields']['extra_' . $entity_def . '_' . $custom_field_key] = $form_elements[$custom_field_key];
                  }
                }

              }
              else {
                $form['content_entities'][$entity_type][$entity_def]['fields'][$custom_field_key] = $custom_field['form_config'];
              }
              // @todo handel other widget type
            }
          }

          foreach ($entity_config['properties'] as $custom_field_key => $custom_field) {
            $entity = NULL;

            if (!empty($custom_field['property'])) {
              if ($custom_field['ref'] == 'user') {
                $form['content_entities'][$entity_type][$entity_def]['properties'][$custom_field_key] = $this->userField();
              }
              if ($custom_field['ref'] == 'user_roles') {
                $form['content_entities'][$entity_type][$entity_def]['properties'][$custom_field_key] = $this->userRolesField();
              }
              if ($custom_field['ref'] == 'status') {
                $form['content_entities'][$entity_type][$entity_def]['properties'][$custom_field_key] = array_intersect_key(
                // The array with all keys.
                  $form_elements[$custom_field_key]['widget']['value'],
                // Keys to be extracted.
                  array_flip(['#title', '#type', '#default_value'])
                );
              }
              if ($custom_field['ref'] == 'user_status') {
                $form['content_entities'][$entity_type][$entity_def]['properties'][$custom_field_key] = array_intersect_key(
                // The array with all keys.
                  $form_elements['account'][$custom_field_key],
                // Keys to be extracted.
                  array_flip(['#title', '#type', '#options'])
                );
              }

            }

          }

          foreach ($form['content_entities'][$entity_type][$entity_def]['fields'] as $field_key => &$field) {
            $this->removeRequiredProperty($field_key, $field);
          }
          foreach ($form['content_entities'][$entity_type][$entity_def]['properties'] as $field_key => &$field) {
            $this->removeRequiredProperty($field_key, $field);
          }
        }
      }
    }

    if ($this->moduleHandler->moduleExists('generator_engine_batch')) {
      // If module generator_engine_batch enabled.
      $form['batch'] = [
        '#title' => $this->t('Batch'),
        '#type' => 'entity_autocomplete',
        '#target_type' => 'node',
        '#selection_handler' => 'default:node',
        '#selection_settings' => [
          'target_bundles' => ['generated_batch'],
          'sort' => [
            'field' => '_none',
            'direction' => 'ASC',
          ],
          'auto_create' => FALSE,
          'auto_create_bundle' => "",
          'match_operator' => 'CONTAINS',
          'match_limit' => 10,
        ],
        '#autocomplete_route_name' => 'system.entity_autocomplete',
        '#autocomplete_route_parameters' => [
          'target_type' => 'node',
          'selection_handler' => 'default:node',
        ],
      ];
    }

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate'),
    ];

    return $form;
  }

  /**
   * Recursively strips the '#required' property from a form element.
   *
   * Some widgets copied wholesale into the generation form (composite or
   * multi-value elements) carry '#required' on nested sub-elements, not
   * just on the top-level element. This walks the element tree and unsets
   * '#required' wherever it appears, so the generation form never forces
   * a value the generator doesn't need.
   *
   * @param string $field_key
   *   Machine name of the field the element belongs to.
   * @param array $element
   *   The form element (render array), passed by reference.
   */
  protected function removeRequiredProperty($field_key, array &$element) {
    if (isset($element['#required'])) {
      $element['#required'] = FALSE;
    }

    foreach ($element as $key => &$value) {
      // Only descend into child elements (non-'#' keys); property keys
      // like '#options' or '#selection_settings' hold plain data, not
      // nested form elements.
      if (is_string($key) && isset($key[0]) && $key[0] === '#') {
        continue;
      }
      if (is_array($value)) {
        $this->removeRequiredProperty($field_key, $value);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $target_entities = $form_state->getValue('content_entities');
    $batch_content = $form_state->getValue('batch');

    $enable_batch_api_process = $this->config('generator_engine.settings')->get('enable_batch_api_process') ?? 1;

    // Normal way (trace or debug)
    if (!$enable_batch_api_process) {
      $result = $this->helpers->generateEngine($target_entities, $batch_content);

      $message = generator_engine_handle_generate_result_batch($result['batch_id'], count($result['items']));
      $this->messenger()->addMessage($message);
    }
    // Batch processing (big data)
    else {
      $result = $this->helpers->generateEngineBatch($target_entities, $batch_content);

      $batch = [
        'title' => $this->t('Generate Entities for @total items', ['@total' => count($result['items'])]),
        'operations' => [],
        'finished' => 'Drupal\generator_engine\Form\GeneratorEngineEntitiesForm::stepGeneratedFinished',
        'init_message' => $this->t('Starting processing @total items', ['@total' => count($result['items'])]),
        'progress_message' => $this->t('Processed @current out of @total'),
        'error_message' => $this->t('Error while processing'),
      ];
      foreach ($result['items'] as $item) {
        // TO DO Send only obj param and initiate class.
        $batch['operations'][] = [$item['callback_function'], [$item['params']]];

      }
      // $batch['operations'][] = ['Drupal\generator_engine\Classes\GeneratorEngineBatch::storeBatch',[$batch_content]];
      batch_set($batch);
    }
  }

  /**
   * Batch finished.
   */
  public static function stepGeneratedFinished($success, $results, $operations) {
    $message = generator_engine_handle_generate_result_batch($results['generated_batch'] ?? NULL, $results['success'] ?? 0);
    // A static batch callback, so the container must be reached directly.
    \Drupal::messenger()->addMessage($message);

    if (!$success) {
      \Drupal::messenger()
        ->addError(\Drupal::translation()->translate('Generation finished with an error.'));
    }
  }

  /**
   * Builds the generation element for a file or image field.
   *
   * @param string $field_title
   *   The field label.
   *
   * @return array
   *   A form element.
   */
  public function fileField($field_title) {
    return [
      '#type' => 'checkbox',
      '#title' => $field_title,
    ];
  }

  /**
   * Builds the generation element for an entity owner.
   *
   * @return array
   *   A form element.
   */
  public function userField() {

    return [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Choose owner user'),
      '#target_type' => 'user',
    ];
  }

  /**
   * Builds the generation element for the user roles property.
   *
   * @return array
   *   A form element.
   */
  public function userRolesField() {
    $entity = User::create();
    $form_build = $this->entityTypeManager->getFormObject('user', 'default')->setEntity($entity);
    $form_elements = $this->formBuilder->getForm($form_build);

    return array_intersect_key(
    // The array with all keys.
      $form_elements['account']['roles'],
    // Keys to be extracted.
      array_flip(['#title', '#type', '#options', '#multiple'])
    );
  }

}
