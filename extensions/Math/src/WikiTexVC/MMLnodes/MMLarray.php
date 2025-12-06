<?php

namespace MediaWiki\Extension\Math\WikiTexVC\MMLnodes;

class MMLarray extends MMLbase {

	/**
	 * Constructor for array wrapper of MMLbase (and string) elements
	 *
	 * @param MMLbase|string|null ...$children MMLbase child elements ...$children
	 */
	public function __construct( ...$children ) {
		parent::__construct( '', '', [], ...$children );
	}

	public function __toString(): string {
		return implode( '', $this->children );
	}
}
