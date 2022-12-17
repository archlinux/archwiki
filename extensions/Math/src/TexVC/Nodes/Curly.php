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

	public function render() {
		return $this->arg->inCurlies();
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

	public function name() {
		return 'CURLY';
	}
}
