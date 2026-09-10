<?php

namespace Drupal\generator_engine\Util;

use Drupal\generator_engine\Util\Text\Lorem;
use Drupal\generator_engine\Util\Urls\RandomUrlGenerator;

class RandomValues {

/**
   * Resolves a field-value directive into its final value.
   *
   * Supports "+text" (append to the generated value), "#key" (copy another
   * field's value from the same statement) and "%name" (an allow-listed
   * value generator).
   *
   * @param mixed $original_value
   *   The value already generated for the field.
   * @param string $text
   *   The submitted directive.
   * @param array $data
   *   Field values built so far for this entity.
   *
   * @return mixed
   *   The resolved value, or $text when no directive applies.
   */
public static function prepareString($original_value, $text, $data = []) {
    if (str_contains($text, '+')) {
      return $original_value . ' [' . str_replace('+', '', $text) . ']';
    }
    if (str_contains($text, '#')) {
      return $data[str_replace('#', '', $text)];
    }
    if (str_contains($text, '%')) {
      $name = str_replace('%', '', $text);
      $generators = self::valueGenerators();
      // Only allow-listed names may be invoked. An unrecognised directive is
      // returned verbatim and never executed.
      if (isset($generators[$name])) {
        return ($generators[$name])();
      }
      return $text;
    }
    return $text;
  }

  /**
   * Lists the value generators callable through the "%name" directive.
   *
   * This is deliberately a closed allow-list. The directive previously built
   * a PHP callable out of submitted field data and invoked it, which let any
   * zero-argument function be called from the generation forms, the Drush
   * command and the HTTP API.
   *
   * Subclasses may add entries, but must never resolve a callable from
   * caller-supplied input.
   *
   * @return array
   *   Generator name keyed to a zero-argument callable returning the value.
   */
  public static function valueGenerators() {
    return [
      'lorem_title' => static fn() => implode(' ', array_slice(preg_split('/\s+/', trim(Lorem::ipsum(1))), 0, 4)),
      'lorem_sentence' => static fn() => Lorem::ipsum(1),
      'lorem_paragraph' => static fn() => Lorem::ipsum(5),
      'random_url' => static fn() => RandomUrlGenerator::generate(),
      'random_number' => static fn() => rand(1, 1000),
      'random_birth_date' => static fn() => date('Y-m-d', rand(strtotime('1970-01-01'), strtotime('now -15 years'))),
      'random_old_date' => static fn() => date('Y-m-d', rand(strtotime('1970-01-01'), strtotime('now -1 day'))),
      'random_future_date' => static fn() => date('Y-m-d', rand(strtotime('now +1 day'), strtotime('now +1 year'))),
      'random_float' => static fn() => rand(1, 1000) / 100,
      'random_list' => static fn() => ['item1', 'item2', 'item3'],
    ];
  }


}