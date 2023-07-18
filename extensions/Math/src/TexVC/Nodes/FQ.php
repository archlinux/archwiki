<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\Math\TexVC\Nodes;

use MediaWiki\Extension\Math\TexVC\MMLmappings\BaseMethods;
use MediaWiki\Extension\Math\TexVC\MMLnodes\MMLmrow;
use MediaWiki\Extension\Math\TexVC\MMLnodes\MMLmsubsup;
use MediaWiki\Extension\Math\TexVC\MMLnodes\MMLmunderover;

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
		$bm = new BaseMethods();
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
			if ( trim( $this->getBase()->getArgs()[0] ) === "\\sum" ) {
				$melement = new MMLmunderover();
			}
		}

		// This seems to be the common case
		$mrow = new MMLmrow();
		return $melement->encapsulateRaw(
			$this->getBase()->renderMML( [], $state ) .
			$mrow->encapsulateRaw( $this->getDown()->renderMML( [], $state ) ) .
			$mrow->encapsulateRaw( $this->getUp()->renderMML( [], $state ) ) );
	}
}
