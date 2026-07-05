<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\Math\WikiTexVC\Nodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLbase;

class Fun4 extends TexNode {

	public function __construct(
		protected readonly string $fname,
		protected readonly TexNode $arg1,
		protected readonly TexNode $arg2,
		protected readonly TexNode $arg3,
		protected readonly TexNode $arg4,
	) {
		parent::__construct( $fname, $arg1, $arg2, $arg3, $arg4 );
	}

	public function getFname(): string {
		return $this->fname;
	}

	public function getArg1(): TexNode {
		return $this->arg1;
	}

	public function getArg2(): TexNode {
		return $this->arg2;
	}

	public function getArg3(): TexNode {
		return $this->arg3;
	}

	public function getArg4(): TexNode {
		return $this->arg4;
	}

	/** @inheritDoc */
	public function inCurlies() {
		return $this->render();
	}

	/** @inheritDoc */
	public function render() {
		return '{' . $this->fname . ' ' .
			$this->arg1->inCurlies() .
			$this->arg2->inCurlies() .
			$this->arg3->inCurlies() .
			$this->arg4->inCurlies() .
			'}';
	}

	/** @inheritDoc */
	public function toMMLTree( array $arguments = [], array &$state = [] ): MMLbase {
		return $this->parseToMML( $this->fname, $arguments, $state );
	}

	/** @inheritDoc */
	public function extractIdentifiers( $args = null ) {
		if ( $args == null ) {
			$args = [ $this->arg1, $this->arg2, $this->arg3, $this->arg4 ];
		}
		return parent::extractIdentifiers( $args );
	}

}
