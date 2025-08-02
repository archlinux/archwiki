<?php

namespace phpunit\unit\WikiTexVC\MMLNodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmi;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmn;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmo;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmsubsup;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmsubsup
 *
 * @group Math
 *
 * @license GPL-2.0-or-later
 */
class MMLmsubsupTest extends MediaWikiUnitTestCase {
	public function testConstructor() {
		$mrow = new MMLmsubsup( '', [ 'mathvariant' => 'bold' ] );
		$this->assertEquals( 'msubsup', $mrow->getName() );
		$this->assertEquals( [ 'mathvariant' => 'bold' ], $mrow->getAttributes() );
	}

	public function testTreeConstructor() {
		$mo = new MMLmo( '', [], '&#x222B;' );
		$mi = new MMLmi( '', [], 'x' );
		$mn = new MMLmn( '', [], '1' );
		$mrow = MMLmsubsup::newSubtree( $mo, $mi, $mn, '', [ 'mathvariant' => 'bold' ] );
		$this->assertEquals( 'msubsup', $mrow->getName() );
		$this->assertEquals( [ 'mathvariant' => 'bold' ], $mrow->getAttributes() );
		$this->assertEquals( $mrow->getChildren(), [ $mo, $mi, $mn ] );
	}
}
