<?php

namespace MediaWiki\Extension\Math\WikiTexVC\MMLnodes;

/**
 * Presentation MathML 3 Element
 * name: "mn"
 * description: "number"
 * category: "Token Elements"
 */
class MMLmn extends MMLleaf {

	public function __construct( string $texclass = "", array $attributes = [], string $text = "" ) {
		parent::__construct( "mn", $texclass, $attributes, $text );
	}
}
