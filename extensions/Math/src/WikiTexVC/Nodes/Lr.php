<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\Math\WikiTexVC\Nodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\BaseMethods;
use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\TexConstants\TexClass;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLbase;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmo;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmrow;

class Lr extends TexNode {

	public function __construct(
		private readonly string $left,
		private readonly string $right,
		private readonly TexArray $arg,
	) {
		parent::__construct( $left, $right, $arg );
	}

	public function getLeft(): string {
		return $this->left;
	}

	public function getRight(): string {
		return $this->right;
	}

	public function getArg(): TexArray {
		return $this->arg;
	}

	/** @inheritDoc */
	public function inCurlies() {
		return '{' . $this->render() . '}';
	}

	/** @inheritDoc */
	public function render() {
		return '\\left' . $this->left . $this->arg->render() . '\\right' . $this->right;
	}

	/** @inheritDoc */
	public function toMMLTree( $arguments = [], &$state = [] ): MMLbase {
		// TBD  set attributes for right AND left correctly
		$rightAttrs = [];
		if ( $this->right == "." ) {
			$rightAttrs = [ "fence" => "true", "stretchy" => "true", "symmetric" => "true" ];
		}

		$bm = new BaseMethods();
		$left = $bm->checkAndParseDelimiter( $this->left, $this, [], null, true,
			TexClass::OPEN );
		if ( $left->isEmpty() ) {
			$left = new MMLmo( TexClass::OPEN, [], $this->right );
		}
		$right = $bm->checkAndParseDelimiter( $this->right, $this, $rightAttrs, null, true,
			TexClass::CLOSE );
		if ( $right->isEmpty() ) {
			$right = new MMLmo( TexClass::CLOSE, $rightAttrs, $this->right );
		}
		// Don't apply outer ' inside the LR structure
		unset( $state['deriv'] );
		$inner = $this->getArg()->toMMLTree( [], $state );
		return new MMLmrow( TexClass::INNER, [], $left, $inner, $right );
	}

	/** @inheritDoc */
	public function containsFunc( $target, $args = null ) {
		if ( $args == null ) {
			$args = [ '\\left', '\\right', $this->arg ];
		}
		return parent::containsFunc( $target, $args );
	}

	/** @inheritDoc */
	public function extractIdentifiers( $args = null ) {
		if ( $args == null ) {
			$args = [ $this->arg ];
		}
		return parent::extractIdentifiers( $args );
	}

}
