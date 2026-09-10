<?php

namespace Drupal\generator_engine\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Generates target entities from a JSON document.
 */
class GenerateJsonHighlighterForm extends FormBase {

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
    return 'json_highlight_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    if (empty($this->config('generator_engine.settings')->get('content_entities'))) {
      $this->messenger()->addWarning($this->t('Select target entities on the configuration page before using the JSON generation form.'));
      return [];
    }

    $form['#tree'] = TRUE;
    $form['content_entities'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Generator Engine Entities'),
      '#prefix' => '<div id="learners-fieldset-wrapper">',
      '#suffix' => '</div>',
    ];

    $form['json_data'] = [
      '#type' => 'textarea',
      '#title' => $this->t('JSON Input'),
      '#default_value' => '{}',
      '#attributes' => ['class' => ['json-editor']],
    ];

    // Add paragraph for json example.
    // A "details" element does not render #markup itself, so the example
    // lives in a child element.
    $form['json_example'] = [
      '#type' => 'details',
      '#title' => $this->t('JSON Example'),
      '#open' => FALSE,
      '#prefix' => '<div id="json-example-wrapper">',
      '#suffix' => '</div>',
    ];

    $form['json_example']['content'] = [
      '#markup' => '<pre>
      [
        {
          "node_type": {
            "article": {
              "check": 1,
              "count": "1",
              "fields": {
                "title": "org_name2",
                "field_sector_type": "public",
                "field_approved": 1
              },
              "properties": {
                "uid": null
              }
            }
          }
        }
      ]
      </pre>',
    ];

    $form['json_example']['more'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('More PHP, JSON and YAML examples are in the assets/samples/ folder inside the module.'),
    ];

    $form['#attached']['library'][] = 'generator_engine/json_highlighter';

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit JSON'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $target_entities = json_decode($form_state->getValue('json_data'), TRUE);

    if (json_last_error() !== JSON_ERROR_NONE) {
      $form_state->setErrorByName('json_data', $this->t('Invalid JSON: @message', [
        '@message' => json_last_error_msg(),
      ]));
      return;
    }

    if (!is_array($target_entities)) {
      $form_state->setErrorByName('json_data', $this->t('The JSON input must decode to an array of generation statements.'));
      return;
    }

    foreach ($this->helpers->validateGenerationCounts($target_entities) as $error) {
      $form_state->setErrorByName('json_data', $error);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $json = $form_state->getValue('json_data');
    $target_entities = json_decode($json, TRUE);

    $generateResult = $this->helpers->generateFromArray($target_entities);

    $this->messenger()->addMessage(
      generator_engine_build_result_markup($generateResult, $this->t('JSON generation finished.'))
    );

  }

}
