<?php

namespace Drupal\generator_engine\Commands;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Core\Serialization\Yaml;
use Drupal\generator_engine\HelpersService;
use Drush\Commands\DrushCommands;

/**
 * Drush commands to run the generator_engine entity generation pipeline.
 */
class GenerationCommands extends DrushCommands {

  /**
   * The generation helpers service.
   *
   * @var \Drupal\generator_engine\HelpersService
   */
  protected $helpers;

  /**
   * Constructs a GenerationCommands object.
   *
   * @param \Drupal\generator_engine\HelpersService $helpers
   *   The generation helpers service.
   */
  public function __construct(HelpersService $helpers) {
    parent::__construct();
    $this->helpers = $helpers;
  }

  /**
   * Generates target entities from a JSON, YAML or PHP array file.
   *
   * Runs the same generation pipeline as the "Using JSON"/"Using YAML" admin
   * forms, without going through the browser.
   *
   * @param string $file
   *   Path to a .json, .yml/.yaml, or .php target-entities file. Pass "-" to
   *   read JSON/YAML content from stdin instead.
   * @param array $options
   *   Command options.
   *
   * @command generator_engine:generate
   * @aliases ge-generate ge-gen
   * @option format
   *   Input format: json, yaml, or php. Defaults to auto-detect from the
   *   file extension. Required when reading from stdin.
   * @option batch
   *   Node ID of an existing "generated_batch" node to append the generated
   *   items to.
   * @option exclude
   *   Whether to filter target entities against the configured
   *   entity/bundle allow-list, same as the admin forms. Pass --no-exclude
   *   to disable.
   * @usage generator_engine:generate modules/custom/generator_engine/assets/samples/sample-target-entities.json
   * @usage generator_engine:generate modules/custom/generator_engine/assets/samples/sample-target-entities.yml
   * @usage generator_engine:generate modules/custom/generator_engine/assets/samples/sample-target-entities.php --format=php
   * @usage generator_engine:generate modules/custom/generator_engine/assets/samples/sample-target-entities-dependant.json
   * @usage cat data.yml | drush generator_engine:generate - --format=yaml
   */
  public function generate($file, $options = ['format' => 'auto', 'batch' => NULL, 'exclude' => TRUE]) {
    $format = $options['format'];

    if ($file === '-') {
      if ($format === 'auto') {
        throw new \Exception('The --format option is required when reading from stdin.');
      }
      if ($format === 'php') {
        throw new \Exception('The php format is only supported from a file, not stdin.');
      }
      $target_entities = $this->decode(stream_get_contents(STDIN), $format);
    }
    else {
      if (!is_file($file) || !is_readable($file)) {
        throw new \Exception("File not found or not readable: $file");
      }
      if ($format === 'auto') {
        $format = $this->detectFormat($file);
      }
      if ($format === 'php') {
        // The "php" input format executes the named file so it can define
        // $target_entities, matching the assets/samples/*.php convention.
        //
        // This is reachable from Drush only. The HTTP endpoints in
        // generator_engine_api never select it: GenerateApiController's
        // detectFormat() returns 'yaml' or 'json', and GenerateApiService
        // normalises anything that is not 'yaml' to 'json'. A caller who can
        // run Drush already has shell access to the site, so this grants no
        // privilege they do not already hold.
        $target_entities = NULL;
        include $file;
        if (!isset($target_entities)) {
          throw new \Exception("The PHP file did not define \$target_entities: $file");
        }
      }
      else {
        $target_entities = $this->decode(file_get_contents($file), $format);
      }
    }

    if (!is_array($target_entities)) {
      throw new \Exception('Decoded data is not an array.');
    }

    $result = $this->helpers->generateFromArray($target_entities, $options['batch'], (bool) $options['exclude']);

    $this->output()->writeln(sprintf('Generation finished. %d item(s) generated.', count($result['items'] ?? [])));
    $this->output()->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
  }

  /**
   * Generates users, tags, and content authored by and tagged with them.
   *
   * @command generator_engine:seed_users_tags_content
   * @aliases ge-seed-utc
   * @option users_count
   *   Number of users to generate. Defaults to 4.
   * @option tags_count
   *   Number of "tags" taxonomy terms to generate. Defaults to 4.
   * @option articles_count
   *   Number of "article" nodes to generate. Defaults to 4.
   * @option pages_count
   *   Number of "page" nodes to generate. Defaults to 4.
   * @usage generator_engine:seed_users_tags_content
   * @usage generator_engine:seed_users_tags_content --users_count=2 --tags_count=6 --articles_count=3 --pages_count=3
   */
  public function seedUsersTagsContent(
    $options = [
      'users_count' => 4,
      'tags_count' => 4,
      'articles_count' => 4,
      'pages_count' => 4,
    ]
  ) {
    $text = $this->helpers->runUsersTagsContentProcedure([], $options);
    $this->output()->writeln($text);
  }

  /**
   * Detects the input format from a file's extension.
   */
  protected function detectFormat($file) {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if ($ext === 'yml' || $ext === 'yaml') {
      return 'yaml';
    }
    if ($ext === 'php') {
      return 'php';
    }
    return 'json';
  }

  /**
   * Decodes raw JSON or YAML content into a PHP array.
   */
  protected function decode($raw, $format) {
    if ($format === 'yaml') {
      try {
        return Yaml::decode($raw);
      }
      catch (InvalidDataTypeException $e) {
        throw new \Exception('Invalid YAML: ' . $e->getMessage(), 0, $e);
      }
    }

    $data = json_decode($raw, TRUE);
    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new \Exception('Invalid JSON: ' . json_last_error_msg());
    }
    return $data;
  }

}
