<?php
/**
 * MediaWiki math extension
 *
 * @copyright 2002-2023 various MediaWiki contributors
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\Math;

use MediaWiki\Extension\Math\InputCheck\LocalChecker;
use MediaWiki\Extension\Math\TexVC\MMLnodes\MMLmath;
use StatusValue;

/**
 * Converts LaTeX to MathML using PHP
 */
class MathNativeMML extends MathMathML {
	private LocalChecker $checker;

	public function __construct( $tex = '', $params = [] ) {
		parent::__construct( $tex, $params );
		$this->setMode( MathConfig::MODE_NATIVE_MML );
		$this->setPurge();
	}

	protected function doRender(): StatusValue {
		$presentation = $this->getChecker()->getParseTree()->renderMML();
		$root = new MMLmath();
		$this->setMathml( $root->encapsulateRaw( $presentation ) );
		return StatusValue::newGood();
	}

	protected function getChecker(): LocalChecker {
		$this->checker ??= Math::getCheckerFactory()
			->newLocalChecker( $this->tex, $this->getInputType() );
		return $this->checker;
	}

}
