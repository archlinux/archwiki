<?php

namespace MediaWiki\Extension\Math\WikiTexVC\MMLnodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\TexConstants\TexClass;

/**
 * Presentation MathML 3 Element
 * name: "mtable"
 * description: "Table or Matrix"
 * category: "Tables and Matrices"
 */
class MMLmtable extends MMLbase {

	/** defaults to  mtable args as generated from MathJax for align(ed) environment
	 * @inheritDoc
	 */
	public function __construct(
		string $texclass = TexClass::ORD,
		array $attributes = [],
		...$rows ) {
		parent::__construct( "mtable", $texclass, $attributes, ...$rows );
	}
}
