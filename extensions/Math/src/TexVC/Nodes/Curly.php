<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\Math\TexVC\Nodes;

class Curly extends TexNode {

	/** @var TexArray */
	private $arg;

	public function __construct( TexArray $arg ) {
		parent::__construct( $arg );
		$this->arg = $arg;
	}

	/**
	 * @return TexArray
	 */
	public function getArg(): TexArray {
		return $this->arg;
	}

	public function render() {
		return $this->arg->inCurlies();
	}

	public function renderMML( $arguments = [], $state = [] ) {
		// J4T set17
		//$mmlRow = new MMLmrow();
		//return $mmlRow->encapsulate(parent::renderMML($arguments));
		return parent::renderMML( $arguments, $state );
	}

	public function inCurlies() {
		return $this->render();
	}

	public function extractSubscripts() {
		return $this->arg->extractSubscripts();
	}

	public function getModIdent() {
		return $this->arg->getModIdent();
	}

}
