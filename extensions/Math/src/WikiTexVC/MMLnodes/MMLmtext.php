<?php

namespace MediaWiki\Extension\Math\WikiTexVC\MMLnodes;

/**
 * Presentation MathML 3 Element
 * name: "mtext"
 * description: "text"
 * category: "Token Elements"
 */
class MMLmtext extends MMLleaf {
	public function __construct( string $texclass = "", array $attributes = [], string $text = "" ) {
		parent::__construct( "mtext", $texclass, $attributes, $text );
	}
}
