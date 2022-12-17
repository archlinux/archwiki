<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\Math\TexVC\Nodes;

class DQ extends TexNode {

	/** @var TexNode */
	private $base;
	/** @var TexNode */
	private $down;

	public function __construct( TexNode $base, TexNode $down ) {
		parent::__construct( $base, $down );
		$this->base = $base;
		$this->down = $down;
	}

	public function render() {
		return $this->base->render() . '_' . $this->down->inCurlies();
	}

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

	public function extractSubscripts() {
		$d = array_merge( [], $this->down->extractSubscripts() );
		$b = $this->base->extractSubscripts();
		if ( isset( $b[0] ) && isset( $d[0] ) ) {
			return [ $b[0] . '_{' . implode( '', $d ) . '}' ];
		}
		return parent::extractSubscripts();
	}

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

	public function name() {
		return 'DQ';
	}
}
