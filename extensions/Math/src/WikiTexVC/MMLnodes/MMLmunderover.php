<?php

namespace MediaWiki\Extension\Math\WikiTexVC\MMLnodes;

/**
 * Presentation MathML 3 Element
 * name: "munderover"
 * description: "attach an underscript-overscript pair to a base"
 * category: "Script and Limit Schemata"
 */
class MMLmunderover extends MMLbase {
	public function __construct( string $texclass = "", array $attributes = [] ) {
		parent::__construct( "munderover", $texclass, $attributes );
	}

	/**
	 * Creates a new subtree element with base and scripts
	 * @param MMLbase $base Main content element
	 * @param MMLbase $underscript Element placed below the base (underscript)
	 * @param MMLbase $overscript Element placed above the base (overscript)
	 * @param string $texclass Optional TeX class for styling
	 * @param array $attributes Additional HTML attributes for the element
	 * @return static New instance with children in order: [base, underscript, overscript]
	 */
	public static function newSubtree( MMLbase $base,
									  MMLbase $underscript,
									  MMLbase $overscript,
									  string $texclass = "",
									  array $attributes = [] ) {
		$instance = new self( $texclass, $attributes );
		$instance->children = [ $base, $underscript, $overscript ];
		return $instance;
	}
}
