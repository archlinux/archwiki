<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\Math\TexVC\Nodes;

use MediaWiki\Extension\Math\TexVC\MMLnodes\MMLmover;
use MediaWiki\Extension\Math\TexVC\MMLnodes\MMLmrow;
use MediaWiki\Extension\Math\TexVC\MMLnodes\MMLmsup;

class UQ extends TexNode {

	/** @var TexNode */
	private $base;
	/** @var TexNode */
	private $up;

	public function __construct( TexNode $base, TexNode $up ) {
		parent::__construct( $base, $up );
		$this->base = $base;
		$this->up = $up;
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

	public function render() {
		return $this->base->render() . '^' . $this->up->inCurlies();
	}

	public function renderMML( $arguments = [], $state = [] ) {
		$mrow = new MMLmrow();
		$mmlBase = new MMLmsup();
		// Sometimes 'overbrace' or similar seems to determine the wrapping element here.
		if ( $this->getBase() instanceof Fun1nb && str_starts_with( $this->getBase()->getArgs()[0], "\\o" ) ) {
			$mmlBase = new MMLmover();
		}
		return $mmlBase->encapsulateRaw(
			$this->base->renderMML( $arguments, $state ) .
			$mrow->getStart() . // up is inferring a new mrow
			$this->up->renderMML( $arguments, $state ) .
			$mrow->getEnd()
		);
	}

}
