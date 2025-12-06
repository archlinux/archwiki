<?php

use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLarray;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmi;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmn;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmo;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLarray
 *
 * @group Math
 *
 * @license GPL-2.0-or-later
 */
class MMLarrayTest extends MediaWikiIntegrationTestCase {
	public function testConstructor() {
		$mi = new MMLmi( '', [], 'x' );
		$mo = new MMLmo( '', [], '+' );
		$mn = new MMLmn( '', [], '5' );
		$array = new MMLarray( $mi, $mo, $mn );

		$this->assertSame( '', $array->getName() );
		$this->assertEquals( [], $array->getAttributes() );
		$this->assertEquals( $array->getChildren(), [ $mi, $mo, $mn ] );
	}

	public function testToString() {
		$mi = new MMLmi( '', [], 'a' );
		$mn = new MMLmn( '', [], '1' );
		$array = new MMLarray( $mi, $mn );

		$this->assertEquals( '<mi>a</mi><mn>1</mn>', (string)$array );
	}
}
