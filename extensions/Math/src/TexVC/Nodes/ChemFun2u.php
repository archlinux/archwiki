<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\Math\TexVC\Nodes;

class ChemFun2u extends TexNode {

	/** @var string */
	private $fname;
	/** @var TexNode */
	private $left;
	/** @var TexNode */
	private $right;

	public function __construct( string $fname, TexNode $left, TexNode $right ) {
		parent::__construct( $fname, $left, $right );
		$this->fname = $fname;
		$this->left = $left;
		$this->right = $right;
	}

	public function inCurlies() {
		return $this->render();
	}

	public function render() {
		return $this->fname . $this->left->inCurlies() . '_' . $this->right->inCurlies();
	}

	public function extractIdentifiers( $args = null ) {
		return [];
	}

	public function name() {
		return 'CHEM_FUN2u';
	}
}
