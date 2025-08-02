<?php

namespace phpunit\unit\WikiTexVC\MMLNodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmi;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmn;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmo;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmpadded;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmpadded
 *
 * @group Math
 *
 * @license GPL-2.0-or-later
 */
class MMLmpaddedTest extends MediaWikiUnitTestCase {
	public function testConstructor() {
		$mi = new MMLmi( '', [], 'x' );
		$mo = new MMLmo( '', [], '+' );
		$mn = new MMLmn( '', [], '5' );
		$mpadded = new MMLmpadded( '', [ 'mathvariant' => 'bold' ], $mi, $mo, $mn );

		$this->assertEquals( 'mpadded', $mpadded->getName() );
		$this->assertEquals( [ 'mathvariant' => 'bold' ], $mpadded->getAttributes() );
		$this->assertEquals( $mpadded->getChildren(), [ $mi, $mo, $mn ] );
	}
}
