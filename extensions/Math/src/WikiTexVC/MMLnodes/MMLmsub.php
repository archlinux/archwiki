<?php

namespace MediaWiki\Extension\Math\WikiTexVC\MMLnodes;

/**
 * Presentation MathML 3 Element
 * name: "msub"
 * description: "attach a subscript to a base"
 * category: "Script and Limit Schemata"
 */
class MMLmsub extends MMLbase {
	public function __construct( string $texclass = "", array $attributes = [] ) {
		parent::__construct( "msub", $texclass, $attributes );
	}

	/**
	 * Creates a new subtree element with base and scripts
	 * @param MMLbase $base Main content element
	 * @param MMLbase $subscript Element placed below the base (subscript)
	 * @param string $texclass Optional TeX class for styling
	 * @param array $attributes Additional HTML attributes for the element
	 * @return static New instance with children in order: [base, subscript]
	 */
	public static function newSubtree( MMLbase $base,
									   MMLbase $subscript,
									   string $texclass = "",
									   array $attributes = [] ) {
		$instance = new self( $texclass, $attributes );
		$instance->children = [ $base, $subscript ];
		return $instance;
	}
}
