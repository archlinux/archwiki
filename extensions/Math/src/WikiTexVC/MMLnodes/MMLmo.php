<?php

namespace MediaWiki\Extension\Math\WikiTexVC\MMLnodes;

/**
 * Presentation MathML 3 Element
 * name: "mo"
 * description: "Operator, Fence, Separator or Accent"
 * category: "Token Elements"
 */
class MMLmo extends MMLleaf {

	public function __construct( string $texclass = "", array $attributes = [], string $text = "" ) {
		parent::__construct( "mo", $texclass, $attributes, $text );
	}
}
