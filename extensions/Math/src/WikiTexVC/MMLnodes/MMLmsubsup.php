<?php

namespace MediaWiki\Extension\Math\WikiTexVC\MMLnodes;

/**
 * Presentation MathML 3 Element
 * name: "msubsup"
 * description: "attach a subscript-superscript pair to a base"
 * category: "Script and Limit Schemata"
 */
class MMLmsubsup extends MMLbase {
	public function __construct( string $texclass = "", array $attributes = [] ) {
		parent::__construct( "msubsup", $texclass, $attributes );
	}

	/**
	 * Creates a new subtree element with base and scripts
	 * @param MMLbase $base Main content element
	 * @param MMLbase $subscript Element placed below the base (subscript)
	 * @param MMLbase $superscript Element placed above the base (superscript)
	 * @param string $texclass Optional TeX class for styling
	 * @param array $attributes Additional HTML attributes for the element
	 * @return static New instance with children in order: [base, subscript, superscript]
	 */
	public static function newSubtree( MMLbase $base,
									   MMLbase $subscript,
									   MMLbase $superscript,
									   string $texclass = "",
									   array $attributes = [] ) {
		$instance = new self( $texclass, $attributes );
		$instance->children = [ $base, $subscript, $superscript ];
		return $instance;
	}
}
