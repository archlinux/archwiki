<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\Math\TexVC\Nodes;

class Mhchem extends Fun1 {

	public function inCurlies() {
		return '{' . $this->render() . '}';
	}

	public function extractIdentifiers( $args = null ) {
		return [];
	}
}
