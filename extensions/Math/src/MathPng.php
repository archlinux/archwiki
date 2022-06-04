<?php
/**
 * MediaWiki math extension
 *
 * @copyright 2002-2018 various MediaWiki contributors
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\Math;

class MathPng extends MathMathML {
	public function __construct( $tex = '', array $params = [] ) {
		parent::__construct( $tex, $params );
		$this->setMode( MathConfig::MODE_PNG );
	}

	public function getHtmlOutput() {
		return $this->getFallbackImage();
	}

}
