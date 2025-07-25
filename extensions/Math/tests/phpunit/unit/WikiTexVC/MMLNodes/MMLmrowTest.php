<?php

namespace phpunit\unit\WikiTexVC\MMLNodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmi;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmn;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmo;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmrow;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmrow
 *
 * @group Math
 *
 * @license GPL-2.0-or-later
 */
class MMLmrowTest extends MediaWikiUnitTestCase {
	public function testConstructor() {
		$mi = new MMLmi( '', [], 'x' );
		$mo = new MMLmo( '', [], '+' );
		$mn = new MMLmn( '', [], '5' );
		$mrow = new MMLmrow( '', [ 'mathvariant' => 'bold' ], $mi, $mo, $mn );

		$this->assertEquals( 'mrow', $mrow->getName() );
		$this->assertEquals( [ 'mathvariant' => 'bold' ], $mrow->getAttributes() );
		$this->assertEquals( $mrow->getChildren(), [ $mi, $mo, $mn ] );
	}
}
