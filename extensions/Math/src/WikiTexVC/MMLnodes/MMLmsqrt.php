<?php

namespace MediaWiki\Extension\Math\WikiTexVC\MMLnodes;

/**
 * Presentation MathML 3 Element
 * name: "msqrt"
 * description: "form a square root (radical without an index)"
 * category: "General Layout Schemata"
 */
class MMLmsqrt extends MMLbase {

	/** @inheritDoc */
	public function __construct( string $texclass = "", array $attributes = [], ...$base ) {
		parent::__construct( "msqrt", $texclass, $attributes, ...$base );
	}
}
