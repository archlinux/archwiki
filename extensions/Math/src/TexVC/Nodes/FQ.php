<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\Math\TexVC\Nodes;

use MediaWiki\Extension\Math\TexVC\MMLmappings\BaseParsing;
use MediaWiki\Extension\Math\TexVC\MMLnodes\MMLmrow;
use MediaWiki\Extension\Math\TexVC\MMLnodes\MMLmstyle;
use MediaWiki\Extension\Math\TexVC\MMLnodes\MMLmsubsup;
use MediaWiki\Extension\Math\TexVC\MMLnodes\MMLmunderover;
use MediaWiki\Extension\Math\TexVC\TexUtil;

class FQ extends TexNode {

	/** @var TexNode */
	private $base;
	/** @var TexNode */
	private $up;
	/** @var TexNode */
	private $down;

	public function __construct( TexNode $base, TexNode $down, TexNode $up ) {
		parent::__construct( $base, $down, $up );
		$this->base = $base;
		$this->up = $up;
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
	public function getUp(): TexNode {
		return $this->up;
	}

	/**
	 * @return TexNode
	 */
	public function getDown(): TexNode {
		return $this->down;
	}

	public function render() {
		return $this->base->render() . '_' . $this->down->inCurlies() . '^' . $this->up->inCurlies();
	}

	public function renderMML( $arguments = [], $state = [] ) {
		if ( array_key_exists( "limits", $state ) ) {
			// A specific FQ case with preceding limits, just invoke the limits parsing manually.
			return BaseParsing::limits( $this, $arguments, $state, "" );
		}

		if ( $this->getArgs()[0]->getLength() == 0 ) {
			// this happens when FQ is located in Sideset (is this a common parsing way?)
			$mrow = new MMLmrow();
			return $mrow->encapsulateRaw( $this->getDown()->renderMML( [], $state ) ) .
				$mrow->encapsulateRaw( $this->getUp()->renderMML( [], $state ) );
		}

		// Not sure if this case is necessary ..
		if ( is_string( $this->getArgs()[0] ) ) {
			return $this->parseToMML( $this->getArgs()[0], $arguments, null );
		}

		$melement = new MMLmsubsup();
		// tbd check for more such cases like TexUtilTest 317
		$base = $this->getBase();
		if ( $base instanceof Literal ) {
			$litArg = trim( $this->getBase()->getArgs()[0] );
			$tu = TexUtil::getInstance();
			// "sum", "bigcap", "bigcup", "prod" ... all are nullary macros.
			if ( $tu->nullary_macro( $litArg ) ) {
				$melement = new MMLmunderover();
			}
		}

		$mrow = new MMLmrow();
		$emptyMrow = "";
		// In cases with empty curly preceding like: "{}_1^2\!\Omega_3^4"
		if ( $this->getBase() instanceof Curly && $this->getBase()->isEmpty() ) {
			$emptyMrow = $mrow->getEmpty();
		}
		// This seems to be the common case
		$inner = $melement->encapsulateRaw(
			$emptyMrow .
			$this->getBase()->renderMML( [], $state ) .
			$mrow->encapsulateRaw( $this->getDown()->renderMML( [], $state ) ) .
			$mrow->encapsulateRaw( $this->getUp()->renderMML( [], $state ) ) );

		if ( $melement instanceof MMLmunderover ) {
			$args = $state['styleargs'] ?? [ "displaystyle" => "true", "scriptlevel" => 0 ];
			$style = new MMLmstyle( "", $args );
			return $style->encapsulateRaw( $inner );
		}

		return $inner;
	}
}
