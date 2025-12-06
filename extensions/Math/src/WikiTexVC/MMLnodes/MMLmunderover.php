<?php

namespace MediaWiki\Extension\Math\WikiTexVC\MMLnodes;

/**
 * Presentation MathML 3 Element
 * name: "munderover"
 * description: "attach an underscript-overscript pair to a base"
 * category: "Script and Limit Schemata"
 */
class MMLmunderover extends MMLbase {
	/** @inheritDoc */
	public function __construct( string $texclass = "", array $attributes = [] ) {
		parent::__construct( "munderover", $texclass, $attributes );
	}

	/**
	 * Creates a new subtree element with base and scripts
	 * @param MMLbase|string $base Main content element
	 * @param MMLbase|string $underscript Element placed below the base (underscript)
	 * @param MMLbase|string $overscript Element placed above the base (overscript)
	 * @param string $texclass Optional TeX class for styling
	 * @param array $attributes Additional HTML attributes for the element
	 * @return static New instance with children in order: [base, underscript, overscript]
	 */
	public static function newSubtree( $base,
									   $underscript,
									   $overscript,
									  string $texclass = "",
									  array $attributes = [] ) {
		$instance = new self( $texclass, $attributes );
		$instance->children = [ $base, $underscript, $overscript ];
		return $instance;
	}
}
