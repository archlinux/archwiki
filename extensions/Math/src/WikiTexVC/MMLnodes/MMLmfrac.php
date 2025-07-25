<?php

namespace MediaWiki\Extension\Math\WikiTexVC\MMLnodes;

/**
 * Presentation MathML 3 Element
 * name: "mfrac"
 * description: "form a fraction from two sub-expressions"
 * category: "General Layout Schemata"
 */
class MMLmfrac extends MMLbase {

	public function __construct( string $texclass = "", array $attributes = [] ) {
		parent::__construct( 'mfrac', $texclass, $attributes );
	}

	/**
	 * Creates a new subtree element with base and scripts
	 * @param MMLbase $numerator Top portion of the fraction
	 * @param MMLbase $denominator Bottom portion of the fraction
	 * @param string $texclass Optional TeX class for styling
	 * @param array $attributes Additional HTML attributes for the element
	 * @return static New instance with children ordered as:
	 *    [base, postsubscript, postsuperscript, presubscript, presuperscript]
	 */
	public static function newSubtree( MMLbase $numerator,
									   MMLbase $denominator,
									   string $texclass = "",
									   array $attributes = [] ) {
		$instance = new self( $texclass, $attributes );
		$instance->children = [ $numerator, $denominator ];
		return $instance;
	}
}
