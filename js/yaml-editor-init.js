/**
 * @file
 * Turns the YAML input textarea into a CodeMirror editor.
 *
 * CodeMirror is an optional external library (see the module README). When it
 * is not installed the textarea is left exactly as it is, so the form stays
 * fully usable without syntax highlighting.
 */
(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.yamlHighlighter = {
    attach: function (context) {
      if (typeof CodeMirror === 'undefined') {
        return;
      }

      once('yaml-editor', 'textarea.yaml-editor', context).forEach(function (element) {
        var editor = CodeMirror.fromTextArea(element, {
          lineNumbers: true,
          mode: 'yaml',
          indentUnit: 2,
          tabSize: 2
        });

        // Copy the editor contents back into the textarea before submitting.
        $(element.form).on('submit', function () {
          element.value = editor.getValue();
        });
      });
    }
  };
})(jQuery, Drupal);
