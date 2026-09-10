<?php

namespace Drupal\generator_engine\Procedures;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\generator_engine\HelpersService;

/**
 * Base class for generator_engine procedures.
 *
 * A procedure is a sequence of generateEngine() statements, threading ids
 * returned from earlier statements into later ones (e.g. authoring a node
 * with a previously-generated user's uid).
 */
class EntitiesProcedures {

  use StringTranslationTrait;

  /**
   * Parameters passed to the procedure.
   *
   * @var array
   */
  public $params;

  /**
   * Options passed to the procedure.
   *
   * @var array
   */
  public $options;

  /**
   * Node ID of the batch collecting the generated entities.
   *
   * @var int|string|null
   */
  public $batchId;

  /**
   * The generation helpers service.
   *
   * @var \Drupal\generator_engine\HelpersService
   */
  public $generateHelpers;

  /**
   * Constructs an EntitiesProcedures object.
   *
   * @param array $params
   *   Parameters for the procedure.
   * @param array $options
   *   Options for the procedure.
   * @param \Drupal\generator_engine\HelpersService $generate_helpers
   *   The generation helpers service.
   */
  public function __construct($params, $options, HelpersService $generate_helpers) {
    $this->params = $params;
    $this->options = $options;
    $this->generateHelpers = $generate_helpers;
  }

  /**
   * Runs the procedure.
   *
   * @return string
   *   A summary of what was generated.
   */
  public function run() {
    return '';
  }

  /**
   * Performs the procedure's generation steps.
   *
   * @param bool $log_progress
   *   Whether to log per-item progress.
   *
   * @return string
   *   A summary of what was generated.
   */
  public function mainLogic($log_progress = TRUE) {
    return '';
  }

  /**
   * Logs a progress message.
   *
   * Procedures can run for a long time, so progress is recorded through the
   * logger rather than written straight to output.
   *
   * @param string $message
   *   The progress message.
   * @param bool $silent
   *   When TRUE the message is discarded.
   */
  public function callConsole($message, $silent = TRUE) {
    if (!$silent) {
      \Drupal::logger('generator_engine')->debug('@message', ['@message' => trim($message)]);
    }
  }

  /**
   * Runs a single target-entities statement through the direct engine path.
   *
   * @param array $target_entities
   *   The generation statement to run.
   *
   * @return array
   *   The engine result.
   */
  public function generateEngineWrapper($target_entities) {
    return $this->generateHelpers->generateEngine($target_entities, $this->batchId, FALSE);
  }

}
