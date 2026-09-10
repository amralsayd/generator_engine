<?php

namespace Drupal\generator_engine\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configures which content entity types and bundles may be generated.
 */
class GeneratorEngineConfigForm extends ConfigFormBase {

  /**
   * The generation helpers service.
   *
   * @var \Drupal\generator_engine\HelpersService
   */
  protected $helpers;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->helpers = $container->get('generator_engine.helpers');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'generator_engine.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'generator_engine_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $entities_types = $this->helpers->getEntitiesConfigFieldsMain();

    $config = $this->config('generator_engine.settings');

    $form['#tree'] = TRUE;

    $form['enable_apis'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable JSON/YAML generation APIs'),
      '#description' => $this->t('Master on/off switch for the HTTP endpoints that validate a JSON/YAML payload and run the generation pipeline: <code>POST /api/generator-engine/{json,yaml,file}</code> from the <strong>Generator Engine API</strong> sub-module, and the matching <code>/rest/*</code> resources from the <strong>Generator Engine API — REST</strong> sub-module. Has no effect unless those sub-modules are enabled. Disabled by default because these endpoints create content.'),
      '#default_value' => $config->get('enable_apis') ?? FALSE,
    ];

    $form['content_entities'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Generator Engine Entities'),
      '#prefix' => '<div id="learners-fieldset-wrapper">',
      '#suffix' => '</div>',
    ];

    foreach ($entities_types as $entity_type => $entity_bundles) {
      $form['content_entities'][$entity_type]['check'] = [
        '#type' => 'checkbox',
        '#title' => $entity_type,
        '#default_value' => $config->get("content_entities.$entity_type.check"),
      ];

      $form['content_entities'][$entity_type]['bundles'] = [
        '#type' => 'fieldset',
        '#states' => [
          'visible' => [
            ':input[name="content_entities[' . $entity_type . '][check]"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $options = [];
      foreach ($entity_bundles as $entity_bundle => $entity_bundle_config) {
        $options[$entity_bundle] = [
          'type' => ['#markup' => $entity_bundle],
        ];

      }

      $header = [
        'type' => $this->t('Types'),
      ];

      $form['content_entities'][$entity_type]['bundles']['items'] = [
        '#type' => 'tableselect',
        '#header' => $header,
        '#options' => $options,
      ];

      if ($config->get("content_entities.$entity_type.bundles.items") > 0) {
        $form['content_entities'][$entity_type]['bundles']['items']['#default_value'] = $config->get("content_entities.$entity_type.bundles.items");
      }

    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $values = $form_state->getValues();

    $this->configFactory->getEditable('generator_engine.settings')
      ->set('content_entities', $values['content_entities'])
      ->set('enable_apis', (bool) $values['enable_apis'])
      ->save();

  }

}
