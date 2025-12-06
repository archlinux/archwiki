<?php

namespace MediaWiki\Extension\Math\WikiTexVC\MMLnodes;

class MMLmath extends MMLbase {

	/** @inheritDoc */
	public function __construct( string $texclass = "", array $attributes = [], ...$children ) {
		$attributes["xmlns"] = "http://www.w3.org/1998/Math/MathML";
		parent::__construct( "math", $texclass, $attributes, ...$children );
	}
}
