<?php

namespace MediaWiki\Extension\Math\WikiTexVC\MMLnodes;

/**
 * Presentation MathML 3 Element
 * name: "munder"
 * description: "attach an underscript to a base"
 * category: "Script and Limit Schemata"
 */
class MMLmunder extends MMLbase {
	public function __construct( string $texclass = "", array $attributes = [] ) {
		parent::__construct( "munder", $texclass, $attributes );
	}

	/**
	 * Creates a new subtree element with base and scripts
	 * @param MMLbase $base Main content element
	 * @param MMLbase $underscript Element placed below the base (subscript)
	 * @param string $texclass Optional TeX class for styling
	 * @param array $attributes Additional HTML attributes for the element
	 * @return static New instance with children in order: [base, underscript]
	 */
	public static function newSubtree( MMLbase $base,
									   MMLbase $underscript,
									   string $texclass = "",
									   array $attributes = [] ) {
		$instance = new self( $texclass, $attributes );
		$instance->children = [ $base, $underscript ];
		return $instance;
	}
}
