Error Reporting
===============

Errors are returned by the Parser and Sanitizer as arrays

    [ $code, $line, $character, ... ]

`$code` is a short string, with values and definitions listed below. `$line`
and `$character` indicate the location of the error. Some errors may have
additional parameters after the character, as described below.

Error Tags
----------

* **at-rule-block-not-allowed**: An at-rule was provided with a block, but that
  type of at-rule cannot take a block. One extra parameter, the name of the
  at-rule in question.

* **at-rule-block-required**: An at-rule needs a block but was not provided
  with one. One extra parameter, the name of the at-rule in question.

* **bad-character-in-url**: An invalid character was encountered while parsing
  a (non-quoted) `url()`.

* **bad-escape**: An invalid character was encountered while parsing an escape
  sequence.

* **bad-value-for-property**: A property was supplied with an invalid or
  unsupported value. One extra parameter, the name of the property in question.

* **expected-at-rule**: An at-rule was expected but something else was found.
  One extra parameter, the name of the at-rule in question.

* **expected-colon**: A colon was expected, but something else was found.

* **expected-declaration**: A declaration was expected, but something else was
  found.

* **expected-declaration-list**: A list of declarations was expected, but
  something else was found.

* **expected-eof**: The end of the input was expected, but there was additional
  input.

* **expected-ident**: An identifier was expected, but something else was found.

* **expected-page-margin-at-rule**: One of the margin at-rules for `page` was
  expected, but something else was found.

* **expected-qualified-rule**: A qualified rule (e.g. a style rule rather than
  an at-rule) was expected, but something else was found.

* **expected-stylesheet**: A stylesheet or list of rules was expected, but
  something else was found.

* **invalid-font-face-at-rule**: The `font-face` rule cannot have anything in
  between the `font-face` and the block.

* **invalid-font-feature-value**: The feature value at-rules inside
  `font-feature-values` cannot have anything in between the at-keyword and the
  block. One extra parameter, the name of the at-rule in question.

* **invalid-font-feature-value-declaration**: The feature value at-rules inside
  a `font-feature-values` map arbitrary identifiers to one or more numbers.
  Either a non-number was provided or an incorrect number of numbers were
  provided. One extra parameter, the name of the at-rule in question.

* **invalid-font-feature-values-font-list**: An invalid font list was supplied
  for `font-feature-values`.

* **invalid-import-value**: An invalid URL and/or media query was provided for
  `import`.

* **invalid-keyframe-name**: An invalid keyframe name was supplied for
  `keyframes`.

* **invalid-media-query**: An invalid media query was supplied for `media`.

* **invalid-namespace-value**: An invalid value was supplied for `namespace`.

* **invalid-page-margin-at-rule**: The margin at-rules inside `page` cannot
  have anything in between the at-keyword and the block. One extra parameter,
  the name of the at-rule in question.

* **invalid-page-rule-content**: A `page` at-rule may contain only
  declarations and margin at-rules. Something else was found.

* **invalid-page-selector**: An invalid page selector was supplied for `page`.

* **invalid-selector-list**: An invalid selector was supplied for a style rule.

* **invalid-supports-condition**: An invalid condition was supplied for
  `supports`.

* **misordered-rule**: A rule ordering requirement (e.g. that `import` must
  come before `namespace`) was violated.

* **missing-font-feature-values-font-list**: No font list was supplied for
  `font-feature-values`.

* **missing-import-source**: No URL was supplied for `import`.

* **missing-keyframe-name**: No keyframe name was supplied for `keyframes`.

* **missing-namespace-value**: No namespace was supplied for `namespaces`.

* **missing-selector-list**: No selector was supplied for a style rule.

* **missing-supports-condition**: No condition was supplied for `supports`.

* **missing-value-for-property**: No value was supplied for a property. One
  extra parameter, the name of the property in question.

* **newline-in-string**: A newline was encountered inside a quoted string.

* **recursion-depth-exceeded**: Blocks and/or functions were nested too deeply.
  The rest of the input was ignored.

* **unclosed-comment**: An unclosed comment was encountered.

* **unclosed-string**: An unclosed string was encountered.

* **unclosed-url**: An unclosed (non-quoted) `url()` was encountered.

* **unexpected-eof**: The end of the input was encountered unexpectedly.

* **unexpected-eof-in-block**: The end of the input was encountered
  unexpectedly while parsing a block.

* **unexpected-eof-in-function**: The end of the input was encountered
  unexpectedly while parsing a function.

* **unexpected-eof-in-rule**: The end of the input was encountered unexpectedly
  while parsing a rule.

* **unexpected-token-in-declaration-list**: An unexpected token was encountered
  while parsing a list of declarations or a list of declarations-and-at-rules.

* **unrecognized-property**: A property was encountered that is not recognized
  in the current context.

* **unrecognized-rule**: A rule was encountered that is not recognized in the
  current context.
