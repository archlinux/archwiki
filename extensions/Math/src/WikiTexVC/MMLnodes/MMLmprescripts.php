<?php

namespace MediaWiki\Extension\Math\WikiTexVC\MMLnodes;

class MMLmprescripts extends MMLbase {
	public function __construct() {
		parent::__construct( "mprescripts" );
	}

	/**
	 * Get string presentation of current element
	 * @return string
	 */
	public function __toString(): string {
		return "<mprescripts/>";
	}
}
