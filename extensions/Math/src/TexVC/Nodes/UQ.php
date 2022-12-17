<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\Math\TexVC\Nodes;

class UQ extends TexNode {

	/** @var TexNode */
	private $base;
	/** @var TexNode */
	private $up;

	public function __construct( TexNode $base, TexNode $down ) {
		parent::__construct( $base, $down );
		$this->base = $base;
		$this->up = $down;
	}

	public function render() {
		return $this->base->render() . '^' . $this->up->inCurlies();
	}

	public function name() {
		return 'UQ';
	}
}
