<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\Math\TexVC\Nodes;

use InvalidArgumentException;

class TexNode {

	/** @var list<TexNode|string> */
	protected $args;

	/**
	 * Creates a TexNode
	 * @param TexNode|string ...$args arguments for this node
	 */
	public function __construct( ...$args ) {
		foreach ( $args as $arg ) {
			if ( !( $arg instanceof TexNode || is_string( $arg ) ) ) {
				throw new InvalidArgumentException( 'Wrong input type specified in args.' );
			}
		}
		$this->args = $args;
	}

	public function render() {
		return array_reduce( $this->args, function ( $out, $child ) {
			return $out . $this->renderChild( $child );
		}, '' );
	}

	public function renderChild( $child ) {
		if ( $child instanceof TexNode ) {
			return $child->render();
		}

		return $child;
	}

	public function getLength(): ?int {
		if ( isset( $this->args[0] ) ) {
			return count( $this->args );
		} else {
			return 0;
		}
	}

	/**
	 * Wraps the rendered result in curly brackets.
	 * @return string rendered result in curlies.
	 */
	public function inCurlies() {
		return '{' . $this->render() . '}';
	}

	public function extractIdentifiers( $args = null ) {
		if ( $args == null ) {
			$args = $this->args;
		}
		$output = [];

		array_walk( $args, static function ( $value, $key ) use ( &$output )  {
			if ( is_string( $value ) ) {
				array_push( $output, $value );
			} else {
				$output = array_merge( $output, $value->extractIdentifiers() );
			}
		} );

		return $output;
	}

	public function containsFunc( $target ) {
		$output = false;
		array_walk( $this->args, static function ( $value, $key ) use ( &$target, &$output ) {
			if ( $output ) {
				// Do not check the other items, if some function has been found already.
				return;
			}
			if ( $value instanceof TexNode ) {
				$ret = $value->containsFunc( $target );
			} else {
				$ret = TexNode::texContainsFunc( $target, $value );
			}
			if ( $ret ) {
				$output = true;
			}
		} );

		return $output;
	}

	public function extractSubscripts() {
		return [];
	}

	public function getModIdent() {
		return [];
	}

	/**
	 * strings can contain function references only in a few specific
	 * forms, which we test for here.
	 *
	 * @param string|array $target
	 * @param string $t Tex to be checked
	 * @return string|bool rendered LaTeX string or false if not found.
	 */
	public static function texContainsFunc( $target, string $t ) {
		// may have trailing '(', '[', '\\{' or " "
		$t = preg_replace( '/(\(|\[|\\\\{| )$/', '', $t );

		// special case #1: \\operatorname {someword}
		$m = preg_match( '/^\\\\operatorname \{([^\\\\]*)}$/', $t );
		if ( $m == 1 ) {
			return self::match( $target, '\\operatorname' );
		}

		// special case #2: \\mbox{\\somefunc}
		$matches = [];
		$m = preg_match( '/^\\\\mbox\{(\\\\.*)}$/', $t, $matches );
		if ( $m == 1 ) {
			$match1 = self::match( $target, '\\mbox' );
			if ( $match1 ) {
				return $match1;
			}
			return self::match( $target, $matches[1] );
		}

		// special case #3: \\color, \\pagecolor, \\definecolor
		$matches = [];
		$m = preg_match( '/^(\\\\(color|pagecolor|definecolor))/', $t, $matches );
		if ( $m == 1 ) {
			return self::match( $target, $matches[1] );
		}

		// special case #4: \\mathbb, \\mathrm
		$matches = [];
		$m = preg_match( '/^(\\\\math..) \{(\\.*)}$/', $t, $matches );
		if ( $m == 1 ) {
			$match = self::match( $target, $matches[1] );
			if ( $match ) {
				return $match;
			}
			return self::match( $target, $matches[2] );
		}

		// protect against using random strings as keys in target
		if ( substr( $t, 0, 1 ) === '\\' ) {
			return self::match( $target, $t );
		} else {
			return false;
		}
	}

	/**
	 * Matches a string against a string, array, or set target.
	 * @param string|array $target
	 * @param string $str
	 * @return bool|string matching value or false
	 */
	public static function match( $target, string $str ) {
		if ( is_array( $target ) ) {
			$output = false;
			array_walk( $target, static function ( $value, $key ) use ( &$output, &$str )  {
				if ( $output ) {
					return;
				}
				$output = TexNode::match( $value, $str );
			} );
			return $output;
		}
		if ( is_string( $target ) ) {
			return $target === $str ? $str : false;
		}

		return $target[$str] ? $str : false;
	}
}
