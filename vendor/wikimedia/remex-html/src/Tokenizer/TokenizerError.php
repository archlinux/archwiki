<?php

namespace Wikimedia\RemexHtml\Tokenizer;

class TokenizerError extends \Exception {
}

// Retain the old namespace for backwards compatibility.
class_alias( TokenizerError::class, 'RemexHtml\Tokenizer\TokenizerError' );
