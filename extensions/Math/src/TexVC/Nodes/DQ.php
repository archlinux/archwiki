<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\Math\TexVC\Nodes;

use MediaWiki\Extension\Math\TexVC\MMLmappings\BaseMethods;
use MediaWiki\Extension\Math\TexVC\MMLnodes\MMLmrow;
use MediaWiki\Extension\Math\TexVC\MMLnodes\MMLmsub;

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

	/**
	 * @return TexNode
	 */
	public function getBase(): TexNode {
		return $this->base;
	}

	/**
	 * @return TexNode
	 */
	public function getDown(): TexNode {
		return $this->down;
	}

	public function render() {
		return $this->base->render() . '_' . $this->down->inCurlies();
	}

	public function renderMML( $arguments = [], $state = [] ) {
		$res = BaseMethods::checkAndParse( $this->base->getArgs()[0], $arguments, null, $this );
		if ( $res ) {
			return $res;
		} else {
			// Otherwise use default fallback
			$mmlMrow = new MMLmrow();
			$msub = new MMLmsub();
			return $msub->encapsulateRaw(
				$this->base->renderMML( [], $state ) .
				$mmlMrow->encapsulateRaw( $this->down->renderMML( [], $state ) ) );
		}
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

}
