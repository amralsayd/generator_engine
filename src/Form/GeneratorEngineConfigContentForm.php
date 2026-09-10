<?php

namespace Drupal\generator_engine\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configures which entity form displays drive the generation forms.
 */
class GeneratorEngineConfigContentForm extends ConfigFormBase {

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
    return 'generator_engine_config_content_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $entities_types = $this->helpers->getEntitiesConfigFieldsMain();

    $config = $this->config('generator_engine.settings');

    $form['#tree'] = TRUE;

    $form['enable_batch_api_process'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Batch API Process'),
      '#description' => $this->t('When enabled, entity generation runs through the Batch API (recommended for large data sets). When disabled, entities are generated in a single normal form submission.'),
      '#default_value' => $config->get('enable_batch_api_process') ?? 1,
    ];

    $form['form_entities'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Generator Engine Entities'),
      '#prefix' => '<div id="forms-fieldset-wrapper">',
      '#suffix' => '</div>',
    ];

    foreach ($entities_types as $entity_type => $entity_bundles) {
      $form['form_entities'][$entity_type]['check'] = [
        '#type' => 'checkbox',
        '#title' => $entity_type,
        '#default_value' => $config->get("form_entities.$entity_type.check"),
      ];

      $form['form_entities'][$entity_type]['bundles'] = [
        '#type' => 'fieldset',
        '#states' => [
          'visible' => [
            ':input[name="form_entities[' . $entity_type . '][check]"]' => ['checked' => TRUE],
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

      $form['form_entities'][$entity_type]['bundles']['items'] = [
        '#type' => 'tableselect',
        '#header' => $header,
        '#options' => $options,
      ];

      if ($config->get("form_entities.$entity_type.bundles.items") > 0) {
        $form['form_entities'][$entity_type]['bundles']['items']['#default_value'] = $config->get("form_entities.$entity_type.bundles.items");
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
      ->set('form_entities', $values['form_entities'])
      ->set('enable_batch_api_process', $values['enable_batch_api_process'])
      ->save();

  }

}
