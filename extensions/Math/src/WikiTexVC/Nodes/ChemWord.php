<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\Math\WikiTexVC\Nodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\TexConstants\TexClass;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLbase;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmrow;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmtext;

class ChemWord extends TexNode {
	public function __construct(
		private readonly TexNode $left,
		private readonly TexNode $right,
	) {
		parent::__construct( $left, $right );
	}

	public function getLeft(): TexNode {
		return $this->left;
	}

	public function getRight(): TexNode {
		return $this->right;
	}

	/** @inheritDoc */
	public function render() {
		return $this->left->render() . $this->right->render();
	}

	/** @inheritDoc */
	public function toMMLTree( array $arguments = [], array &$state = [] ): MMLbase {
		// If the right has an empty literal value, content is resolved as dash
		$right = $this->getRight()->getArgs()[0] == ""
			? "-"
			: $this->getRight()->toMMLTree( [], $state );
		return new MMLmrow(
			TexClass::ORD,
			[],
			new MMLmrow(
				TexClass::ORD,
				[],
				new MMLmtext(
					"",
					[ "mathcolor" => "red" ],
					(string)$this->getLeft()->toMMLTree( [], $state )
				),
				new MMLmtext(
					"",
					[],
					(string)$right
				)
			)
		);
	}

	/** @inheritDoc */
	public function extractIdentifiers( $args = null ) {
		return [];
	}

}
