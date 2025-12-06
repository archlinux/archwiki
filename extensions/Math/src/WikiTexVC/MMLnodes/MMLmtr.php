<?php

namespace MediaWiki\Extension\Math\WikiTexVC\MMLnodes;

/**
 * Presentation MathML 3 Element
 * name: "mtr"
 * description: "row in a table or matrix"
 * category: "Tables and Matrices"
 */
class MMLmtr extends MMLbase {

	/** @inheritDoc */
	public function __construct( string $texclass = "", array $attributes = [], ...$cells ) {
		parent::__construct( "mtr", $texclass, $attributes, ...$cells );
	}

}
