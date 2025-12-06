<?php

namespace MediaWiki\Extension\Math\WikiTexVC\MMLnodes;

/**
 * Presentation MathML 3 Element
 * name: "merror"
 * description: "enclose a syntax error message from a preprocessor"
 * category: "General Layout Schemata"
 */
class MMLmerror extends MMLbase {

	/** @inheritDoc */
	public function __construct( string $texclass = "", array $attributes = [], ...$children ) {
		parent::__construct( "merror", $texclass, $attributes, ...$children );
	}
}
