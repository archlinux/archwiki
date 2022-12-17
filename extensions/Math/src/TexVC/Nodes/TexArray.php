<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\Math\TexVC\Nodes;

use InvalidArgumentException;

class TexArray extends TexNode {

	public function __construct( ...$args ) {
		$nargs = [];
		self::checkInput( $args );

		foreach ( $args as &$arg ) {
			if ( $arg != null ) {
				array_push( $nargs, $arg );
			}
		}
		parent::__construct( ...$args );
	}

	public function inCurlies() {
		if ( isset( $this->args[0] ) && count( $this->args ) == 1 ) {
			return $this->args[0]->inCurlies();
		} else {
			return '{' . $this->render() . '}';
		}
	}

	public function extractSubscripts() {
		$y = [];

		foreach ( $this->args as $x ) {
			$y = array_merge( $y, $x->extractSubscripts() );
		}
		if ( isset( $this->args[0] ) && ( count( $this->args ) == count( $y ) ) ) {
			return implode( '', $y );
		}
		return [];
	}

	public function extractIdentifiers( $args = null ) {
		if ( $args == null ) {
			$args = $this->args;
		}
		$list = parent::extractIdentifiers( $args );
		$outpos = 0;
		$offset = 0;
		$int = 0;

		for ( $inpos = 0; $inpos < count( $list ); $inpos++ ) {
			$outpos = $inpos - $offset;
			switch ( $list[$inpos] ) {
				case '\'':
					$list[$outpos - 1] .= '\'';
					$offset++;
					break;
				case '\\int':
					$int++;
					$offset++;
					break;
				case '\\mathrm{d}':
				case 'd':
					if ( $int ) {
						$int--;
						$offset++;
						break;
					}
				// no break
				default:
					if ( isset( $list[0] ) ) {
						$list[$outpos] = $list[$inpos];
					}
			}
		}
		return array_slice( $list, 0, count( $list ) - $offset );
	}

	public function getModIdent() {
		$y = [];

		foreach ( $this->args as $x ) {
			$y = array_merge( $y, $x->getModIdent() );
		}

		if ( isset( $this->args[0] ) && ( count( $this->args ) == count( $y ) ) ) {
			return implode( "", $y );
		}
		return [];
	}

	public function push( ...$elements ) {
		self::checkInput( $elements );

		array_push( $this->args, ...$elements );
	}

	/**
	 * @throws InvalidArgumentException if first value not defined
	 * @return TexNode|string first value
	 */
	public function first() {
		if ( isset( $this->args[0] ) ) {
			return $this->args[0];
		} else {
			throw new InvalidArgumentException( 'Input arguments not have been filled.' );
		}
	}

	/**
	 * @throws InvalidArgumentException if second value not defined
	 * @return TexNode|string second value
	 */
	public function second() {
		if ( isset( $this->args[1] ) ) {
			return $this->args[1];
		} else {
			throw new InvalidArgumentException( 'Input arguments not have been filled.' );
		}
	}

	public function name() {
		return 'ARRAY';
	}

	/**
	 * @throws InvalidArgumentException if args not of correct type
	 * @param TexNode[] $args input args
	 * @return void
	 */
	private static function checkInput( $args ): void {
		foreach ( $args as $arg ) {
			if ( !( $arg instanceof TexNode ) ) {
				throw new InvalidArgumentException( 'Wrong input type specified in input elements.' );
			}
		}
	}
}
