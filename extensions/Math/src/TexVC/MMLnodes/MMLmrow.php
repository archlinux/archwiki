<?php

namespace MediaWiki\Extension\Math\TexVC\MMLnodes;

use MediaWiki\Extension\Math\TexVC\MMLmappings\TexConstants\TexClass;

/**
 * Presentation MathML 3 Element
 * name: "mrow"
 * description: "group any number of sub-expressions horizontally"
 * category: "General Layout Schemata"
 */
class MMLmrow extends MMLbase {

	public function __construct( string $texclass = TexClass::ORD, array $attributes = [] ) {
		parent::__construct( "mrow", $texclass, $attributes );
	}
}
