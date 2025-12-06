<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\Math\WikiTexVC\Nodes;

class Declh extends TexNode {

	/** @var string */
	private $fname;
	/** @var TexArray */
	private $arg;

	public function __construct( string $fname, TexArray $arg ) {
		parent::__construct( $fname, $arg );
		$this->fname = $fname;
		$this->arg = $arg;
	}

	public function getFname(): string {
		return $this->fname;
	}

	public function getArg(): TexArray {
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
		return $this->parseToMML( $this->fname, $arguments, null );
	}

	/** @inheritDoc */
	public function extractIdentifiers( $args = null ) {
		if ( $args == null ) {
			$args = [ $this->arg ];
		}

		$identifier = parent::extractIdentifiers( $args );
		return $identifier ? [ implode( '', $identifier ) ] : [];
	}

	/** @inheritDoc */
	public function extractSubscripts() {
		$f = $this->fname;
		// @see
		// http://tex.stackexchange.com/questions/98406/which-command-should-i-use-for-textual-subscripts-in-math-mode
		// cf https://phabricator.wikimedia.org/T56818 a is always RM
		// for f there are only four cases
		switch ( $f ) {
			case '\\rm':
				$f = '\\mathrm';
				break;
			case '\\it':
				$f = '\\mathit';
				break;
			case '\\cal':
				$f = '\\mathcal';
				break;
			case '\\bf':
				$f = '\\mathbf';
		}

		$x = $this->arg->extractSubscripts();
		if ( isset( $x[0] ) ) {
			return [ $f . '{' . $x . '}' ];
		}
		return parent::extractSubscripts();
	}

}
