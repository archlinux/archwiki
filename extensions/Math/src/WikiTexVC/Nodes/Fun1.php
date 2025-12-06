<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\Math\WikiTexVC\Nodes;

use InvalidArgumentException;
use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\TexConstants\TexClass;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLbase;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmo;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmover;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmpadded;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmrow;
use MediaWiki\Extension\Math\WikiTexVC\TexUtil;

class Fun1 extends TexNode {

	/** @var string */
	protected $fname;
	/** @var TexNode */
	protected $arg;

	public function __construct( string $fname, TexNode $arg ) {
		parent::__construct( $fname, $arg );
		$this->fname = $fname;
		$this->arg = $arg;
	}

	public function getFname(): string {
		return $this->fname;
	}

	public function getArg(): TexNode {
		return $this->arg;
	}

	/** @inheritDoc */
	public function inCurlies() {
		return $this->render();
	}

	/** @inheritDoc */
	public function render() {
		return '{' . $this->fname . ' ' . $this->arg->inCurlies() . '}';
	}

	/** @inheritDoc */
	public function toMMLTree( array $arguments = [], array &$state = [] ) {
		$cb = TexUtil::getInstance()->callback( trim( $this->fname ) );
		if ( is_string( $cb ) && preg_match( '#^' .
				preg_quote( self::class ) .
				'::(?<method>\\w+)$#', $cb, $m ) ) {
			return $this->{$m['method']}( $arguments, $state );
		}

		return $this->parseToMML( $this->fname, $arguments, null );
	}

	public function createMover( string $inner, array $moArgs = [] ): MMLbase {
		return new MMLmrow( TexClass::ORD, [],
			new MMLmrow( TexClass::ORD, [],
				( new MMLmover() )::newSubtree( $this->args[1]->toMMLTree(),
					new MMLmo( "", $moArgs, $inner ) )
			)
		);
	}

	/** @inheritDoc */
	public function extractIdentifiers( $args = null ) {
		if ( $args == null ) {
			$args = [ $this->arg ];
		}
		$tu = TexUtil::getInstance();
		$letterMods = array_keys( $tu->getBaseElements()['is_letter_mod'] );
		if ( in_array( $this->fname, $letterMods, true ) ) {
			$ident = $this->arg->getModIdent();
			if ( !isset( $ident[0] ) ) {
				return parent::extractIdentifiers( $args );
			}
			// in difference to javascript code: taking first element of array here.
			return [ $this->fname . '{' . $ident[0] . '}' ];

		} elseif ( array_key_exists( $this->fname, $tu->getBaseElements()['ignore_identifier'] ) ) {
			return [];
		}

		return parent::extractIdentifiers( $args );
	}

	/** @inheritDoc */
	public function extractSubscripts() {
		return $this->getSubs( $this->arg->extractSubscripts() );
	}

	/** @inheritDoc */
	public function getModIdent() {
		return $this->getSubs( $this->arg->getModIdent() );
	}

	private function getSubs( array $subs ): array {
		$letterMods = array_keys( TexUtil::getInstance()->getBaseElements()['is_letter_mod'] );

		if ( isset( $subs[0] ) && in_array( $this->fname, $letterMods, true ) ) {
			// in difference to javascript code: taking first element of array here.
			return [ $this->fname . '{' . $subs[0] . '}' ];
		}
		return [];
	}

	private function lap(): MMLmrow {
		$name = $this->fname;
		if ( trim( $name ) === "\\rlap" ) {
			$args = [ "width" => "0" ];
		} elseif ( trim( $name ) === "\\llap" ) {
			$args = [ "width" => "0", "lspace" => "-1width" ];
		} else {
			throw new InvalidArgumentException(
				"Unsupported function for lap: $name"
			);
		}
		return new MMLmrow( TexClass::ORD, [],
			new MMLmpadded( "", $args, $this->getArg()->toMMLTree() ) );
	}

}
