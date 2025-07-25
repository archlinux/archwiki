<?php

namespace phpunit\unit\WikiTexVC\MMLNodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmath;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmi;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmn;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmo;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmath
 *
 * @group Math
 *
 * @license GPL-2.0-or-later
 */
class MMLmathTest extends MediaWikiUnitTestCase {
	public function testConstructor() {
		$mi = new MMLmi( '', [], 'x' );
		$mo = new MMLmo( '', [], '+' );
		$mn = new MMLmn( '', [], '5' );
		$mmath = new MMLmath( '', [ 'mathvariant' => 'bold' ], $mi, $mo, $mn );

		$this->assertEquals( 'math', $mmath->getName() );
		$this->assertEquals(
			[
				'mathvariant' => 'bold',
				'xmlns' => 'http://www.w3.org/1998/Math/MathML'
			],
			$mmath->getAttributes() );
		$this->assertEquals( $mmath->getChildren(), [ $mi, $mo, $mn ] );
	}
}
