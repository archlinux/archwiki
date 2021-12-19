<?php

namespace Wikimedia\RemexHtml\TreeBuilder;

class TreeBuilderError extends \Exception {
}

// Retain the old namespace for backwards compatibility.
class_alias( TreeBuilderError::class, 'RemexHtml\TreeBuilder\TreeBuilderError' );
