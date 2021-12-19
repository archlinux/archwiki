<?php

namespace Wikimedia\RemexHtml\TreeBuilder;

/**
 * An interface for things that can go in the ActiveFormattingElements list
 */
interface FormattingElement {
}

// Retain the old namespace for backwards compatibility.
class_alias( FormattingElement::class, 'RemexHtml\TreeBuilder\FormattingElement' );
