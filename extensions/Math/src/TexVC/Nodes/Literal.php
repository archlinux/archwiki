<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\Math\TexVC\Nodes;

use MediaWiki\Extension\Math\TexVC\TexUtil;

class Literal extends TexNode {

	/** @var string */
	private $arg;
	private $literals;
	private $extendedLiterals;

	public function __construct( string $arg ) {
		parent::__construct( $arg );
		$this->arg = $arg;
		$tu = new TexUtil();
		$this->literals = array_keys( $tu->getBaseElements()['is_literal'] );
		$this->extendedLiterals = $this->literals;
		array_push( $this->extendedLiterals,  '\\infty', '\\emptyset' );
	}

	public function extractIdentifiers( $args = null ) {
		return $this->getLiteral( $this->literals, '/^([a-zA-Z\']|\\\\int)$/' );
	}

	public function extractSubscripts() {
		return $this->getLiteral( $this->extendedLiterals, '/^([0-9a-zA-Z+\',-])$/' );
	}

	public function getModIdent() {
		if ( $this->arg === '\\ ' ) {
			return [ '\\ ' ];
		}
		return $this->getLiteral( $this->literals, '/^([0-9a-zA-Z\'])$/' );
	}

	private function getLiteral( $lit, $regexp ) {
		$s = trim( $this->arg );
		if ( preg_match( $regexp, $s ) == 1 ) {
			return [ $s ];
		} elseif ( in_array( $s, $lit ) ) {
			 return [ $s ];
		} else {
			return [];
		}
	}

	public function name() {
		return 'LITERAL';
	}
}
