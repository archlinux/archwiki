<?php

namespace MediaWiki\Extension\Math\WikiTexVC\MMLnodes;

/**
 * Presentation MathML 3 Element
 * name: "mtd"
 * description: "one entry in a table or matrix"
 * category: "Tables and Matrices"
 */
class MMLmtd extends MMLbase {

	/** @inheritDoc */
	public function __construct( string $texclass = "", array $attributes = [], ...$children ) {
		parent::__construct( "mtd", $texclass, $attributes, ...$children );
	}

}
