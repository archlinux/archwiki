<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\Math\TexVC\Nodes;

class ChemWord extends TexNode {

	/** @var TexNode */
	private $left;
	/** @var TexNode */
	private $right;

	public function __construct( TexNode $left, TexNode $right ) {
		parent::__construct( $left, $right );
		$this->left = $left;
		$this->right = $right;
	}

	public function inCurlies() {
		return $this->render();
	}

	public function render() {
		return $this->left->render() . $this->right->render();
	}

	public function extractIdentifiers( $args = null ) {
		return [];
	}

	public function name() {
		return 'CHEM_WORD';
	}
}
