<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\Math\WikiTexVC\Nodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLbase;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmsub;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmunder;

class DQ extends FQ {
	public function __construct(
		private readonly TexNode $base,
		private readonly TexNode $down,
	) {
		parent::__construct( $base, $down, new TexArray() );
	}

	/** @inheritDoc */
	public function render() {
		return $this->base->render() . '_' . $this->down->inCurlies();
	}

	/** @inheritDoc */
	public function extractIdentifiers( $args = null ) {
		$d = $this->down->extractSubscripts();
		$b = $this->base->extractIdentifiers();
		if ( is_array( $b ) && count( $b ) > 1 ) {
			return parent::extractIdentifiers();
		}

		if ( isset( $b[0] ) && $b[0] === '\'' ) {
			return array_merge( $b, $d );
		}

		if ( isset( $d[0] ) && isset( $b[0] ) ) {
			if ( $b[0] === '\\int' ) {
				return array_merge( $b, $d );
			}
			return [ $b[0] . '_{' . $d[0] . '}' ];
		}

		return parent::extractIdentifiers();
	}

	/** @inheritDoc */
	public function extractSubscripts() {
		$d = array_merge( [], $this->down->extractSubscripts() );
		$b = $this->base->extractSubscripts();
		if ( isset( $b[0] ) && isset( $d[0] ) ) {
			return [ $b[0] . '_{' . implode( '', $d ) . '}' ];
		}
		return parent::extractSubscripts();
	}

	/** @inheritDoc */
	public function getModIdent() {
		$d = $this->down->extractSubscripts();
		$b = $this->base->getModIdent();
		if ( isset( $b[0] ) && $b[0] === '\'' ) {
			return [];
		}
		if ( isset( $d[0] ) && isset( $b[0] ) ) {
			return [ $b[0] . '_{' . $d[0] . '}' ];
		}

		return parent::getModIdent();
	}

	protected function newMmlElement( bool $above, MMLbase $base, MMLbase $down, MMLbase $up ): MMLbase {
		if ( $above ) {
			return MMLmunder::newSubtree( $base, $down );
		} else {
			return MMLmsub::newSubtree( $base, $down );
		}
	}
}
