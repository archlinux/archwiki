<?php

namespace RemexHtml\TreeBuilder;
use RemexHtml\PropGuard;

/**
 * A pseudo-element used as a marker or bookmark in the list of active formatting elements
 */
class Marker implements FormattingElement {
	public $nextAFE;
	public $prevAFE;
	public $nextNoah;
	public $type;

	public function __construct( $type ) {
		$this->type = $type;
	}

	public function __set( $name, $value ) {
		PropGuard::set( $this, $name, $value );
	}
}

