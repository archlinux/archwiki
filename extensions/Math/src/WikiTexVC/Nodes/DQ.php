<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\Math\WikiTexVC\Nodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\BaseParsing;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmrow;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmsub;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmunder;
use MediaWiki\Extension\Math\WikiTexVC\TexUtil;

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

	/** @inheritDoc */
	public function render() {
		return $this->base->render() . '_' . $this->down->inCurlies();
	}

	/** @inheritDoc */
	public function renderMML( $arguments = [], &$state = [] ) {
		if ( array_key_exists( "limits", $state ) ) {
			// A specific DQ case with preceding limits, just invoke the limits parsing manually.
			return BaseParsing::limits( $this, $arguments, $state, "" );
		}

		if ( !$this->isEmpty() ) {
			if ( $this->getBase()->containsFunc( "\underbrace" ) ) {
				$outer = new MMLmunder();
			} else {
				$outer = new MMLmsub();
				if ( ( $state['styleargs']['displaystle'] ?? 'true' ) === 'true' ) {
					$tu = TexUtil::getInstance();
					if ( $tu->operator( trim( $this->base->render() ) ) ) {
						$outer = new MMLmunder();
					}
				}
			}
			// Otherwise use default fallback
			$mmlMrow = new MMLmrow();
			$inner_state = [ 'styleargs' => $state['styleargs'] ?? [] ];
			$baseRendering = $this->base->renderMML( $arguments, $inner_state );
			// In cases with empty curly preceding like: "{}_pF_q" or _{1}
			if ( trim( $baseRendering ) === "" ) {
				$baseRendering = ( new MMLmrow() )->getEmpty();
			}
			return $outer->encapsulateRaw(
				$baseRendering .
				$mmlMrow->encapsulateRaw( $this->down->renderMML( $arguments, $state ) ) );
		}

		return "";
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

}
