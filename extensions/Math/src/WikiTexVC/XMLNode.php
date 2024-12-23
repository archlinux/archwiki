<?php
namespace MediaWiki\Extension\Math\WikiTexVC;

/**
 * This is a helper class for MMLComparator currently
 * to load MathML Strings to PHP objects.
 */
class XMLNode {
	/** @var mixed */
	public $value;
	/** @var self[] */
	public $children;

	public function __construct( $value ) {
		$this->value = $value;
		$this->children = [];
	}
}
