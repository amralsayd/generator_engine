<?php

namespace Drupal\generator_engine\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\Component\Serialization\Exception\InvalidDataTypeException;

/**
 * Generates target entities from a YAML document.
 */
class GenerateYamlHighlighterForm extends FormBase {

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
    $instance->setConfigFactory($container->get('config.factory'));
    $instance->setMessenger($container->get('messenger'));
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'yaml_highlight_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    if (empty($this->config('generator_engine.settings')->get('content_entities'))) {
      $this->messenger()->addWarning($this->t('Select target entities on the configuration page before using the YAML generation form.'));
      return [];
    }

    $form['#tree'] = TRUE;
    $form['content_entities'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Generator Engine Entities'),
      '#prefix' => '<div id="learners-fieldset-wrapper">',
      '#suffix' => '</div>',
    ];

    $form['yaml_data'] = [
      '#type' => 'textarea',
      '#title' => $this->t('YAML Input'),
      '#default_value' => '',
      '#attributes' => ['class' => ['yaml-editor']],
    ];

    // Add paragraph for yaml example.
    // A "details" element does not render #markup itself, so the example
    // lives in a child element.
    $form['yaml_example'] = [
      '#type' => 'details',
      '#title' => $this->t('YAML Example'),
      '#open' => FALSE,
      '#prefix' => '<div id="yaml-example-wrapper">',
      '#suffix' => '</div>',
    ];

    $form['yaml_example']['content'] = [
      '#markup' => '<pre>
      - node_type:
          article:
            check: 1
            count: "1"
            fields:
              title: org_name2
              field_sector_type: public
              field_approved: 1
            properties:
              uid: null
      </pre>',
    ];

    $form['yaml_example']['more'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('More PHP, JSON and YAML examples are in the assets/samples/ folder inside the module.'),
    ];

    $form['#attached']['library'][] = 'generator_engine/yaml_highlighter';

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit YAML'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    try {
      $target_entities = Yaml::decode($form_state->getValue('yaml_data'));
    }
    catch (InvalidDataTypeException $e) {
      $form_state->setErrorByName('yaml_data', $this->t('Invalid YAML: @message', [
        '@message' => $e->getMessage(),
      ]));
      return;
    }

    if (!is_array($target_entities)) {
      $form_state->setErrorByName('yaml_data', $this->t('The YAML input must decode to an array of generation statements.'));
      return;
    }

    foreach ($this->helpers->validateGenerationCounts($target_entities) as $error) {
      $form_state->setErrorByName('yaml_data', $error);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $yaml = $form_state->getValue('yaml_data');

    try {
      $target_entities = Yaml::decode($yaml);
    }
    catch (InvalidDataTypeException $e) {
      $this->messenger()->addError($this->t('Invalid YAML: @message', [
        '@message' => $e->getMessage(),
      ]));
      return;
    }

    $generateResult = $this->helpers->generateFromArray($target_entities);

    $this->messenger()->addMessage(
      generator_engine_build_result_markup($generateResult, $this->t('YAML generation finished.'))
    );

  }

}
