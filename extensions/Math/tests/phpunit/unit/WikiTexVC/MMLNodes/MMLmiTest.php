<?php

use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmi;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmi
 *
 * @group Math
 *
 * @license GPL-2.0-or-later
 */
class MMLmiTest extends MediaWikiUnitTestCase {
	public function testConstructor() {
		$mi = new MMLmi( '', [ 'mathvariant' => 'bold' ], 'x' );
		$this->assertEquals( "mi", $mi->getName() );
		$this->assertEquals( "x", $mi->getText() );
	}
}
