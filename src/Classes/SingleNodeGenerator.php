<?php

namespace Drupal\generator_engine\Classes;

use Drupal\Core\Entity\EntityInterface;
use Drupal\generator_engine\Util\Text\Lorem;
use Drupal\generator_engine\Util\Urls\RandomUrlGenerator;
use Drupal\image\Plugin\Field\FieldType\ImageItem;
use Drupal\generator_engine\Util\RandomValues;
/**
 * Builds and saves one entity from a generation statement.
 */
abstract class SingleNodeGenerator implements SingleNodeGeneratorInterface {

  /**
   * The entity type being generated.
   *
   * @var string
   */
  public $contentType;

  /**
   * The bundle being generated.
   *
   * @var string
   */
  public $contentBundle;

  /**
   * Submitted field values for the bundle.
   *
   * @var array
   */
  public $fieldsData;

  /**
   * Generation configuration for the bundle's fields.
   *
   * @var array
   */
  public $fieldsConfig;

  public function __construct($content_type, $content_bundle, $fields_data, $fields_config) {
    $this->contentType = $content_type;
    $this->contentBundle = $content_bundle;
    $this->fieldsData = $fields_data;
    $this->fieldsConfig = $fields_config;
  }

  /**
   * {@inheritdoc}
   */
  public function generateSingleNode() {

    // @todo apply intial fata for node, tax , user or pargraph
    // Make it a single class, change the type, call the parent function
    // and apply the custom values.
    // $data = $this->prepareIntialData();
    $data = [
      $this->fieldsConfig[$this->contentBundle]['entity_type_property'] => $this->contentBundle,
      $this->fieldsConfig[$this->contentBundle]['entity_title_property'] => $this->contentBundle . ' ' . rand(1000, 9999),
    ];

    if (!empty($this->fieldsData[$this->contentBundle]['fields'])) {
      $custom_fields = $this->fieldsData[$this->contentBundle]['fields'];
      foreach ($custom_fields as $custom_field_key => $custom_field) {

        $current_field_config = $this->fieldsConfig[$this->contentBundle]['custom_fields'][$custom_field_key];
        if ($current_field_config['bind'] == 'ef' && ($custom_field != '!random_single' && $custom_field != '!random_multiple')) {
          if (is_array($custom_field)) {
            if ($current_field_config['mode'] == 'custom') {
              $data[$custom_field_key] = $custom_field;
            }
            else {
              foreach ($custom_field as $value) {
                $data[$custom_field_key][] = ['target_id' => $value];
              }
            }
          }
          else {
            $data[$custom_field_key] = ['target_id' => $custom_field];
          }
        }
        elseif ($current_field_config['bind'] == 'value' && !empty($custom_field)) {
          if (is_array($custom_field)) {
            $data[$custom_field_key] = array_values($custom_field);
          }
          elseif (!empty($data[$custom_field_key]) || str_contains($custom_field, '#') || str_contains($custom_field, '%')) {
            $data[$custom_field_key] = RandomValues::prepareString(@$data[$custom_field_key], $custom_field, $data);
          }
          else {
            if (is_numeric($custom_field) && floor($custom_field) != $custom_field) {
              $custom_field = number_format($custom_field, 2);
            }

            $data[$custom_field_key] = $custom_field;
          }

        }
        elseif ($current_field_config['bind'] == 'uri' && !empty($custom_field)) {
          if (is_array($custom_field)) {
            $data[$custom_field_key] = $custom_field;
          }
          elseif (!empty($data[$custom_field_key]) || str_contains($custom_field, '#') || str_contains($custom_field, '%')) {
            $data[$custom_field_key] = RandomValues::prepareString(@$data[$custom_field_key], $custom_field, $data);
          }
        }
        elseif ($current_field_config['bind'] == 'date' && !empty($custom_field)) {
          $data[$custom_field_key] = $custom_field->format('Y-m-d');
        }
      }
    }

    if (!empty($this->fieldsData[$this->contentBundle]['properties'])) {
      $custom_fields = $this->fieldsData[$this->contentBundle]['properties'];
      foreach ($custom_fields as $custom_field_key => $custom_field) {
        $current_field_config = $this->fieldsConfig[$this->contentBundle]['properties'][$custom_field_key];
        if ($custom_field_key == 'uid' && !empty($custom_field)) {
          $data[$custom_field_key] = $custom_field;
        }
        if ($custom_field_key == 'status') {
          $data[$custom_field_key] = $custom_field;
        }
        elseif ($custom_field_key == 'uid' && empty($custom_field) && \Drupal::currentUser()->isAnonymous()) {
          // @todo put the admin account is fixed account or constant to avoid performance issue
          $data[$custom_field_key] = 1;
        }
      }
    }
    // Let other modules edit the generated field structure before the
    // entity is created.
    // @see hook_generator_engine_entity_data_alter()
    $this->alterEntityData($data);

    $entity_class = $this->fieldsConfig[$this->contentBundle]['entity_class'];
    $node = $entity_class::create($data);

    // Some fields must bind after the entity is created to get the right
    // value binding.
    if (!empty($this->fieldsData[$this->contentBundle]['fields'])) {
      $custom_fields = $this->fieldsData[$this->contentBundle]['fields'];
      foreach ($custom_fields as $custom_field_key => $custom_field) {
        $current_field_config = $this->fieldsConfig[$this->contentBundle]['custom_fields'][$custom_field_key];

        if ($current_field_config['bind'] == 'file' && $custom_field) {
          if (str_contains($custom_field, '$')) {
            // @todo try to generate multiple of files or images using pattern number$type then split
            $file = generator_engine_generate_custom_file($custom_field);
            $file_values = [
              'target_id' => $file->id(),
              'alt' => $file->label(),
              'title' => $file->label(),
            ];
            $node->$custom_field_key->setValue($file_values);
          }
          else {
            $node->$custom_field_key->generateSampleItems(1);
          }
        }
        elseif ($current_field_config['bind'] == 'image' && $custom_field) {
          // @todo 1: we need to check the files extinstion in the field configuration because if the field type 'file' and extintions in configs imgaes devel genrate will generate file not image!
          // @todo 2: try to stor the result as array and follow the preivous foreach to avoid reloop on fields.
          $field_definition = $node->get($custom_field_key)->getFieldDefinition();
          // Scoped to this field: a shared accumulator would leak the values
          // of earlier image fields into every later one.
          $values = [ImageItem::generateSampleValue($field_definition)];
          $node->$custom_field_key->setValue($values);
        }
        elseif ($current_field_config['bind'] == 'media' && $custom_field) {
          $media = generator_engine_generate_custom_media_file($custom_field, $current_field_config['extra']);
          $file_values = [
            'target_id' => $media->id(),
            'alt' => $media->label(),
            'title' => $media->label(),
          ];
          $node->$custom_field_key->setValue($file_values);
        }
        elseif (($current_field_config['bind'] == 'ef' || $current_field_config['bind'] == 'value') && $custom_field === '!random_single') {
          $node->$custom_field_key->generateSampleItems(1);
        }
        elseif (($current_field_config['bind'] == 'ef' || $current_field_config['bind'] == 'value')  && $custom_field === '!random_multiple') {
          $node->$custom_field_key->generateSampleItems(rand(1, 5));
        }
      }
    }

    $node->save();

    // Let other modules access the generated entity.
    // @see hook_generator_engine_entity_generated()
    $this->entityGenerated($node);

    return [
      $this->contentType => [
        $this->contentBundle => $node->id(),
      ],
    ];
  }

  /**
   * Builds the context array shared by the single entity generation hooks.
   *
   * @return array
   *   Information about the entity being generated: the entity type, the
   *   bundle, the submitted target entity data, the entities configuration
   *   and the generator instance itself.
   */
  protected function generationContext() {
    return [
      'entity_type' => $this->contentType,
      'bundle' => $this->contentBundle,
      'fields_data' => $this->fieldsData,
      'fields_config' => $this->fieldsConfig,
      'generator' => $this,
    ];
  }

  /**
   * Lets other modules alter the field values before the entity is created.
   *
   * @param array $data
   *   The values that are about to be passed to the entity ::create().
   *
   * @see hook_generator_engine_entity_data_alter()
   */
  protected function alterEntityData(array &$data) {
    $context = $this->generationContext();
    \Drupal::moduleHandler()->alter('generator_engine_entity_data', $data, $context);
  }

  /**
   * Lets other modules react to a single generated (and saved) entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The generated entity, already saved.
   *
   * @see hook_generator_engine_entity_generated()
   */
  protected function entityGenerated(EntityInterface $entity) {
    $context = $this->generationContext();
    \Drupal::moduleHandler()->invokeAll('generator_engine_entity_generated', [$entity, $context]);
  }

  /**
   * Batch callback that generates one entity and records progress.
   *
   * @param array $params
   *   Generation parameters, including the generator object under "obj".
   * @param array|\ArrayAccess $context
   *   The batch context.
   */
  public static function generateSingleNodeBatch($params, &$context) {

    // Use the $context['sandbox'] at your convenience to store the
    // information needed to track progression between successive calls.
    if (empty($context['sandbox'])) {
      $context['sandbox'] = [];
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['current_node'] = 0;
      $context['sandbox']['generated_entities'] = [];

      // Save node count for the termination message.
      $context['sandbox']['max'] = 30;

    }
    if (!isset($context['results']['success'])) {
      $context['results']['success'] = 0;
    }

    $context['sandbox']['progress']++;

    $result = $params['obj']->generateSingleNode();
    $entity_id = $result[$params['entity_type']][$params['bundle_type']];

    $context['results']['generated_entities'][] = $result;
    $context['sandbox']['generated_entities'][] = $result;

    // Update our progress information.
    $context['results']['success']++;
    $context['sandbox']['current_node'] = $entity_id;
    // Invoked statically as a batch callback, so $this->t() is unavailable.
    $context['message'] = \Drupal::translation()->translate('Running batch "@id": @bundle @entity_id', [
      '@id' => $params['entity_type'],
      '@bundle' => $params['bundle_type'],
      '@entity_id' => $entity_id,
    ]);

  }

  ///**
  // * Resolves a field-value directive into its final value.
  // *
  // * Supports "+text" (append to the generated value), "#key" (copy another
  // * field's value from the same statement) and "%name" (an allow-listed
  // * value generator).
  // *
  // * @param mixed $original_value
  // *   The value already generated for the field.
  // * @param string $text
  // *   The submitted directive.
  // * @param array $data
  // *   Field values built so far for this entity.
  // *
  // * @return mixed
  // *   The resolved value, or $text when no directive applies.
  // */
  //public function prepareString($original_value, $text, $data = []) {
  //  if (str_contains($text, '+')) {
  //    return $original_value . ' [' . str_replace('+', '', $text) . ']';
  //  }
  //  if (str_contains($text, '#')) {
  //    return $data[str_replace('#', '', $text)];
  //  }
  //  if (str_contains($text, '%')) {
  //    $name = str_replace('%', '', $text);
  //    $generators = $this->valueGenerators();
  //    // Only allow-listed names may be invoked. An unrecognised directive is
  //    // returned verbatim and never executed.
  //    if (isset($generators[$name])) {
  //      return ($generators[$name])();
  //    }
  //    return $text;
  //  }
  //  return $text;
  //}
//
  ///**
  // * Lists the value generators callable through the "%name" directive.
  // *
  // * This is deliberately a closed allow-list. The directive previously built
  // * a PHP callable out of submitted field data and invoked it, which let any
  // * zero-argument function be called from the generation forms, the Drush
  // * command and the HTTP API.
  // *
  // * Subclasses may add entries, but must never resolve a callable from
  // * caller-supplied input.
  // *
  // * @return array
  // *   Generator name keyed to a zero-argument callable returning the value.
  // */
  //protected function valueGenerators() {
  //  return [
  //    'lorem_title' => static fn() => implode(' ', array_slice(preg_split('/\s+/', trim(Lorem::ipsum(1))), 0, 4)),
  //    'lorem_sentence' => static fn() => Lorem::ipsum(1),
  //    'lorem_paragraph' => static fn() => Lorem::ipsum(5),
  //    'random_url' => static fn() => RandomUrlGenerator::generate(),
  //    'random_number' => static fn() => rand(1, 1000),
  //    'random_birth_date' => static fn() => date('Y-m-d', rand(strtotime('1970-01-01'), strtotime('now -15 years'))),
  //    'random_old_date' => static fn() => date('Y-m-d', rand(strtotime('1970-01-01'), strtotime('now -1 day'))),
  //    'random_future_date' => static fn() => date('Y-m-d', rand(strtotime('now +1 day'), strtotime('now +1 year'))),
  //    'random_float' => static fn() => rand(1, 1000) / 100,
  //    'random_list' => static fn() => ['item1', 'item2', 'item3'],
  //  ];
  //}

}
