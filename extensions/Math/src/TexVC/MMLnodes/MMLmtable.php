<?php

namespace MediaWiki\Extension\Math\TexVC\MMLnodes;

use MediaWiki\Extension\Math\TexVC\MMLmappings\TexConstants\TexClass;

/**
 * Presentation MathML 3 Element
 * name: "mtable"
 * description: "Table or Matrix"
 * category: "Tables and Matrices"
 */
class MMLmtable extends MMLbase {

	public function __construct( string $texclass = TexClass::ORD, array $attributes = [] ) {
		parent::__construct( "mtable", $texclass, $attributes );
	}
}
