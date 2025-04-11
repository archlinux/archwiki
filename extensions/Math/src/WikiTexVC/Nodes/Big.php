<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\Math\WikiTexVC\Nodes;

class Big extends TexNode {

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

	/** @inheritDoc */
	public function inCurlies() {
		return $this->render();
	}

	/** @inheritDoc */
	public function render() {
		return '{' . $this->fname . ' ' . $this->arg . '}';
	}

	/** @inheritDoc */
	public function renderMML( $arguments = [], &$state = [] ): string {
		return $this->parseToMML( $this->fname, $arguments, null );
	}

	/** @inheritDoc */
	public function extractIdentifiers( $args = null ) {
		return [];
	}

}
