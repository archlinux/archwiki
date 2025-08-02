<?php

namespace MediaWiki\Extension\Math\WikiTexVC\MMLnodes;

/**
 * Presentation MathML 3 Element
 * name: "mi"
 * description: "identifier"
 * category: "Token Elements"
 */
class MMLmi extends MMLleaf {

	public function __construct( string $texclass = "", array $attributes = [], string $text = "" ) {
		parent::__construct( "mi", $texclass, $attributes, $text );
	}
}
