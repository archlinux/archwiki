<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\Math\WikiTexVC\Nodes;

use InvalidArgumentException;
use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\BaseMethods;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLarray;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLbase;

class TexNode {

	/** @var list<self|string> */
	protected $args;

	/**
	 * Creates a TexNode
	 * @param self|string ...$args arguments for this node
	 */
	public function __construct( ...$args ) {
		foreach ( $args as $arg ) {
			if ( !( $arg instanceof self || is_string( $arg ) ) ) {
				throw new InvalidArgumentException( 'Wrong input type specified in args.' );
			}
		}
		$this->args = $args;
	}

	/**
	 * @param string $input
	 * @param array $passedArgs
	 * @param mixed|null $operatorContent
	 * @return MMLbase|string|null
	 */
	protected function parseToMML( $input, $passedArgs, $operatorContent ) {
		$parsed = BaseMethods::checkAndParse( $input, $passedArgs, $operatorContent, $this );
		if ( $parsed ) {
			return $parsed;
		}
		$name = strtoupper( self::class );

		return BaseMethods::generateMMLError( "Not implemented $name for $input" );
	}

	/**
	 * @return self[]|string[]
	 */
	public function getArgs(): array {
		return $this->args;
	}

	/**
	 * @return string
	 */
	public function render() {
		$out = '';
		foreach ( $this->args as $child ) {
			$out .= $child instanceof self ? $child->render() : $child;
		}
		return $out;
	}

	/**
	 * @param array $arguments
	 * @param array &$state
	 * @return MMLbase|string|null
	 */
	public function toMMLTree( array $arguments = [], array &$state = [] ) {
		return new MMLarray( ...array_map(
			fn ( $child ) => $this->processChildMML( $child, $arguments, $state ),
			$this->args
		) );
	}

	/**
	 * @param mixed $child
	 * @param array $arguments
	 * @param array &$state
	 * @return MMLbase|null
	 */
	private function processChildMML( $child, array $arguments, array &$state ): ?MMLbase {
		if ( $child instanceof self ) {
			return $child->toMMLTree( $arguments, $state );
		}
		return null;
	}

	/**
	 * @return bool
	 */
	public function isEmpty() {
		foreach ( $this->args ?? [] as $arg ) {
			if ( $arg instanceof self && !$arg->isEmpty() ) {
				return false;
			}
			if ( is_string( $arg ) && $arg !== '' ) {
				return false;
			}
		}
		return true;
	}

	public function getLength(): int {
		return count( $this->args ?? [] );
	}

	/**
	 * Wraps the rendered result in curly brackets.
	 * @return string rendered result in curlies.
	 */
	public function inCurlies() {
		return '{' . $this->render() . '}';
	}

	/**
	 * @param self[]|string[]|null $args
	 * @return string[]
	 */
	public function extractIdentifiers( $args = null ) {
		$output = [];

		foreach ( $args ?? $this->args as $value ) {
			if ( $value instanceof self ) {
				$output = array_merge( $output, $value->extractIdentifiers() );
			} else {
				$output[] = $value;
			}
		}

		return $output;
	}

	/**
	 * @param string|array $target
	 * @param self[]|string[]|null $args
	 * @return bool
	 */
	public function containsFunc( $target, $args = null ) {
		foreach ( $args ?? $this->args as $value ) {
			if ( $value instanceof self ) {
				$ret = $value->containsFunc( $target );
			} else {
				$ret = self::texContainsFunc( $target, $value );
			}
			if ( $ret ) {
				// Do not check the other items, if some function has been found already.
				return true;
			}
		}

		return false;
	}

	/**
	 * @return string|array
	 */
	public function extractSubscripts() {
		return [];
	}

	/**
	 * @return string|array
	 */
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
		// protect against using random strings as keys in target
		if ( !$t || $t[0] !== '\\' ) {
			return false;
		}

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
			return self::match( $target, '\\mbox' ) ?: self::match( $target, $matches[1] );
		}

		// special case #3: \\color, \\pagecolor, \\definecolor
		$matches = [];
		$m = preg_match( '/^(\\\\(?:page|define)?color) /', $t, $matches );
		if ( $m == 1 ) {
			return self::match( $target, $matches[1] );
		}

		// special case #4: \\mathbb, \\mathrm
		$matches = [];
		$m = preg_match( '/^(\\\\math..) \{(\\\\.*)}$/', $t, $matches );
		if ( $m == 1 ) {
			return self::match( $target, $matches[1] ) ?: self::match( $target, $matches[2] );
		}

		return self::match( $target, $t );
	}

	/**
	 * Matches a string against a string, array, or set target.
	 * @param string|array $target
	 * @param string $str
	 * @return bool|string matching value or false
	 */
	public static function match( $target, string $str ) {
		if ( is_string( $target ) ) {
			return $target === $str ? $str : false;
		}

		foreach ( $target as $key => $value ) {
			// In javascript both types are used to comparison in match functionality
			if ( is_string( $key ) ) {
				if ( $key === $str ) {
					return $str;
				}
			} elseif ( is_array( $value ) ) {
				if ( self::match( $value, $str ) !== false ) {
					return $str;
				}
			} elseif ( $value === $str ) {
				return $str;
			}
		}

		return false;
	}

	public function isCurly(): bool {
		return false;
	}

}
