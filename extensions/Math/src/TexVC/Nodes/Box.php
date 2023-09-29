<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\Math\TexVC\Nodes;

use MediaWiki\Extension\Math\TexVC\MMLnodes\MMLmrow;
use MediaWiki\Extension\Math\TexVC\MMLnodes\MMLmtext;

class Box extends TexNode {

	/** @var string */
	private $fname;
	/** @var string */
	private $arg;

	public function __construct( string $fname, string $arg ) {
		parent::__construct( $fname, $arg );
		$this->fname = $fname;
		$this->arg = $arg;
	}

	/**
	 * @return string
	 */
	public function getFname(): string {
		return $this->fname;
	}

	/**
	 * @return string
	 */
	public function getArg(): string {
		return $this->arg;
	}

	public function inCurlies() {
		return $this->render();
	}

	public function render() {
		return '{' . $this->fname . '{' . $this->arg . '}}';
	}

	public function renderMML( $arguments = [], $state = [] ) {
		$mrow = new MMLmrow();
		$mtext = new MMLmtext();
		return $mrow->encapsulateRaw(
			$mtext->encapsulateRaw( $this->getArg() )
		);
	}

	public function extractIdentifiers( $args = null ) {
		return [];
	}

}
