/**
 * @file
 * Lightweight CodeMirror 5 mode for YAML syntax highlighting.
 *
 * A small, self-contained tokenizer (not the upstream CodeMirror yaml mode)
 * covering the constructs used by the target-entities YAML input: comments,
 * document markers, list dashes, anchors/aliases/tags, mapping keys, quoted
 * strings, numbers and boolean/null atoms.
 */
(function (CodeMirror) {
  'use strict';

  // CodeMirror is an optional external library; do nothing when it is absent.
  if (!CodeMirror) {
    return;
  }

  CodeMirror.defineMode('yaml', function () {
    return {
      startState: function () {
        return {};
      },

      token: function (stream) {
        if (stream.sol() && stream.match(/^(---|\.\.\.)/)) {
          return 'def';
        }

        if (stream.eatSpace()) {
          return null;
        }

        if (stream.peek() === '#') {
          stream.skipToEnd();
          return 'comment';
        }

        if (stream.match(/^-(?=\s|$)/)) {
          return 'meta';
        }

        if (stream.match(/^[&*!][^\s,\[\]{}]+/)) {
          return 'meta';
        }

        if (stream.match(/^"(?:[^"\\]|\\.)*"/) || stream.match(/^'(?:[^']|'')*'/)) {
          return 'string';
        }

        // Mapping key: a run of characters up to a colon followed by
        // whitespace or end of line (not a colon inside a value/URL etc.).
        if (stream.match(/^[^\s\-#'"\[\]{},:][^:]*(?=:(\s|$))/)) {
          return 'property';
        }

        if (stream.match(/^:(\s|$)/)) {
          return 'punctuation';
        }

        if (stream.match(/^-?\d+(\.\d+)?([eE][+-]?\d+)?\b/)) {
          return 'number';
        }

        if (stream.match(/^(true|false|null|~|True|False|Null|TRUE|FALSE|NULL)\b/)) {
          return 'atom';
        }

        if (stream.match(/^[\[\]{},]/)) {
          return 'punctuation';
        }

        stream.next();
        return null;
      }
    };
  });

  CodeMirror.defineMIME('text/x-yaml', 'yaml');
  CodeMirror.defineMIME('text/yaml', 'yaml');
})(window.CodeMirror);
