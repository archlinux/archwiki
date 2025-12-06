<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\Math\WikiTexVC\Nodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\TexConstants\TexClass;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmrow;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmtext;

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

	public function getFname(): string {
		return $this->fname;
	}

	public function getArg(): string {
		return $this->arg;
	}

	/** @inheritDoc */
	public function inCurlies() {
		return $this->render();
	}

	/** @inheritDoc */
	public function render() {
		return '{' . $this->fname . '{' . $this->arg . '}}';
	}

	/** @inheritDoc */
	public function toMMLTree( array $arguments = [], array &$state = [] ) {
		$arg = $this->getArg();

		if ( strlen( $arg ) >= 1 ) {
			// Replace trailing and leading spaces with special space sign
			if ( substr( $arg, -1, 1 ) === " " ) {
				$arg = rtrim( $arg, " " ) . "&#xA0;";
			}
			if ( substr( $arg, 0, 1 ) == " " ) {
				$arg = "&#xA0;" . ltrim( $arg, " " );
			}
		}
		return new MMLmrow( TexClass::ORD, [],
			new MMLmtext( "", [], $arg )
		);
	}

	/** @inheritDoc */
	public function extractIdentifiers( $args = null ) {
		return [];
	}

}
