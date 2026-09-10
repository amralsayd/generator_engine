<?php

namespace Drupal\generator_engine\Classes\Entities\Single;

use Drupal\image\Plugin\Field\FieldType\ImageItem;
use Drupal\generator_engine\Classes\EntitySingleNodeGenerator;
use Drupal\generator_engine\Classes\SingleNodeGeneratorInterface;
use Drupal\generator_engine\Util\RandomValues;
use Drupal\user\Entity\User;

/**
 * Generates a single user account.
 */
class EntityUserSingleGenerator extends EntitySingleNodeGenerator implements SingleNodeGeneratorInterface {

  /**
   * {@inheritdoc}
   */
  public function generateSingleNode() {
    $id = rand(5000000000, 5999999999);

    $data = [
      'name' => 'gen_name_' . $id ,
      'status' => 1,
    ];

    if (!empty($this->fieldsData['user']['fields'])) {
      $custom_fields = $this->fieldsData['user']['fields'];
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
      }
    }

    // Let other modules edit the generated field structure before the
    // account is created.
    // @see hook_generator_engine_entity_data_alter()
    $this->alterEntityData($data);

    $account = User::create($data);
    $account->enforceIsNew();
    $account->setEmail('gen-user-' . $id . time() . '@example.example');

    if (!empty($this->fieldsData['user']['properties'])) {
      $custom_fields = $this->fieldsData['user']['properties'];
      foreach ($custom_fields as $custom_field_key => $custom_field) {
        $current_field_config = $this->fieldsConfig[$this->contentBundle]['properties'][$custom_field_key];
        if ($custom_field_key == 'roles') {
          foreach ($custom_field as $role) {
            if ($role) {
              $account->addRole($role);
            }
          }
        }
        if ($custom_field_key == 'uid' && !empty($custom_field)) {
          $data[$custom_field_key] = $custom_field;
        }
        if ($custom_field_key == 'name' && !empty($custom_field)) {
          $account->setUsername($custom_field);
        }
        if ($custom_field_key == 'status' && is_numeric($custom_field)) {
          $account->set('status', $custom_field);
        }
      }
    }

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
            $account->$custom_field_key->setValue($file_values);
          }
          else {
            $account->$custom_field_key->generateSampleItems(1);
          }
        }
        elseif ($current_field_config['bind'] == 'image' && $custom_field) {
          // @todo 1: we need to check the files extinstion in the field configuration because if the field type 'file' and extintions in configs imgaes devel genrate will generate file not image!
          // @todo 2: try to stor the result as array and follow the preivous foreach to avoid reloop on fields
          // Use the target field's own definition rather than borrowing an
          // article node's field_image, so this works even when that field
          // or bundle does not exist.
          $field_definition = $account->get($custom_field_key)->getFieldDefinition();
          $values = [ImageItem::generateSampleValue($field_definition)];
          $account->$custom_field_key->setValue($values);
        }
        elseif ($current_field_config['bind'] == 'media' && $custom_field) {
          $media = generator_engine_generate_custom_media_file($custom_field, $current_field_config['extra']);

          $file_values = [
            'target_id' => $media->id(),
            'alt' => $media->label(),
            'title' => $media->label(),
          ];
          $account->$custom_field_key->setValue($file_values);
        }
        elseif (($current_field_config['bind'] == 'ef' || $current_field_config['bind'] == 'value') && $custom_field === '!random_single') {
          $account->$custom_field_key->generateSampleItems(1);
        }
        elseif (($current_field_config['bind'] == 'ef' || $current_field_config['bind'] == 'value')  && $custom_field === '!random_multiple') {
          $account->$custom_field_key->generateSampleItems(rand(1, 5));
        }
      }
    }

    $account->save();

    // Let other modules access the generated account.
    // @see hook_generator_engine_entity_generated()
    $this->entityGenerated($account);

    return ['user' => ['user' => $account->id()]];
  }

  /**
   * Batch callback that generates one account and records progress.
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

}
