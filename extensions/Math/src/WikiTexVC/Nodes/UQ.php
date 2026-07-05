<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\Math\WikiTexVC\Nodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLbase;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmi;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmover;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmsup;

class UQ extends FQ {

	public function __construct(
		private readonly TexNode $base,
		private readonly TexNode $up,
	) {
		parent::__construct( $base, new TexArray(), $up );
	}

	/** @inheritDoc */
	public function render() {
		return $this->base->render() . '^' . $this->up->inCurlies();
	}

	protected function newMmlElement( bool $above, MMLbase $base, MMLbase $down, MMLbase $up ): MMLbase {
		if ( $base->isEmpty() ) {
			$base = new MMLmi();
		}
		if ( $above ) {
			return MMLmover::newSubtree( $base, $up );
		} else {
			return MMLmsup::newSubtree( $base, $up );
		}
	}
}
