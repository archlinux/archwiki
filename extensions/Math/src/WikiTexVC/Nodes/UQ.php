<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\Math\WikiTexVC\Nodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\TexConstants\TexClass;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmi;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmover;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmrow;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmsup;
use MediaWiki\Extension\Math\WikiTexVC\TexUtil;

class UQ extends TexNode {

	/** @var TexNode */
	private $base;
	/** @var TexNode */
	private $up;

	public function __construct( TexNode $base, TexNode $up ) {
		parent::__construct( $base, $up );
		$this->base = $base;
		$this->up = $up;
	}

	public function getBase(): TexNode {
		return $this->base;
	}

	public function getUp(): TexNode {
		return $this->up;
	}

	/** @inheritDoc */
	public function render() {
		return $this->base->render() . '^' . $this->up->inCurlies();
	}

	/** @inheritDoc */
	public function toMMLTree( $arguments = [], &$state = [] ) {
		$mmlBase = new MMLmsup();
		$base = $this->getBase();
		$up = $this->getUp();

		$tu = TexUtil::getInstance();

		if ( $base instanceof Fun1 && $tu->over_operator( $base->getFname() ) ) {
			$mmlBase = new MMLmover();
		}

		// If the superscript has empty elements, render them with empty mi elements to prevent browser issues
		if ( $base instanceof TexArray && $base->getLength() == 0 ) {
			$baseRendered = new MMLmi();
		} else {
			$baseRendered = $base->toMMLTree( $arguments, $state ) ?? "";
		}
		if ( $up instanceof TexArray && $up->getLength() == 0 ) {
			$upRendered = new MMLmi();
		} else {
			// up is inferring a new mrow if it has some content
			$upRendered = new MMLmrow( TexClass::ORD, [], $up->toMMLTree( $arguments, $state ) );
		}

		return $mmlBase::newSubtree( $baseRendered, $upRendered );
	}

}
