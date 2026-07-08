<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\Math\WikiTexVC\Nodes;

class ChemFun2u extends TexNode {

	public function getFname(): string {
		return $this->fname;
	}

	public function __construct(
		private readonly string $fname,
		private readonly TexNode $left,
		private readonly TexNode $right,
	) {
		parent::__construct( $fname, $left, $right );
	}

	public function getLeft(): TexNode {
		return $this->left;
	}

	public function getRight(): TexNode {
		return $this->right;
	}

	/** @inheritDoc */
	public function render() {
		return $this->fname . $this->left->inCurlies() . '_' . $this->right->inCurlies();
	}

	/** @inheritDoc */
	public function extractIdentifiers( $args = null ) {
		return [];
	}

}
