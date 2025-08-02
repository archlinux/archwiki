<?php

namespace MediaWiki\Extension\Math\WikiTexVC\MMLnodes;

/**
 * Presentation MathML 3 Element
 * name: "msup"
 * description: "attach a superscript to a base"
 * category: "Script and Limit Schemata"
 */
class MMLmsup extends MMLbase {
	public function __construct( string $texclass = "", array $attributes = [] ) {
		parent::__construct( "msup", $texclass, $attributes );
	}

	/**
	 * Creates a new subtree element with base and scripts
	 * @param MMLbase $base Main content element
	 * @param MMLbase $superscript Element placed above the base (superscript)
	 * @param string $texclass Optional TeX class for styling
	 * @param array $attributes Additional HTML attributes for the element
	 * @return static New instance with children in order: [base, superscript]
	 */
	public static function newSubtree( MMLbase $base,
									   MMLbase $superscript,
									   string $texclass = "",
									   array $attributes = [] ) {
		$instance = new self( $texclass, $attributes );
		$instance->children = [ $base, $superscript ];
		return $instance;
	}
}
