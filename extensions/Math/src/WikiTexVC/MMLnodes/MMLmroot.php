<?php

namespace MediaWiki\Extension\Math\WikiTexVC\MMLnodes;

/**
 * Presentation MathML 3 Element
 * name: "mroot"
 * description: "form a radical with specified index"
 * category: "General Layout Schemata"
 */
class MMLmroot extends MMLbase {

	public function __construct( string $texclass = "", array $attributes = [] ) {
		parent::__construct( "mroot", $texclass, $attributes );
	}

	/**
	 * Creates a new subtree element with base and scripts
	 * @param MMLbase $base Radicand
	 * @param MMLbase $index Root degree
	 * @param string $texclass Optional TeX class for styling
	 * @param array $attributes Additional HTML attributes for the element
	 * @return static New instance with children in order: [base, index]
	 */
	public static function newSubtree( MMLbase $base,
									   MMLbase $index,
									   string $texclass = "",
									   array $attributes = [] ) {
		$instance = new self( $texclass, $attributes );
		$instance->children = [ $base, $index ];
		return $instance;
	}
}
