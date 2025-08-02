<?php

namespace MediaWiki\Extension\Math\Tests\WikiTexVC\MMLnodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmn;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmn
 *
 * @group Math
 *
 * @license GPL-2.0-or-later
 */
class MMLmnTest extends MediaWikiUnitTestCase {
	public function testConstructor() {
		$mn = new MMLmn( '', [ 'mathvariant' => 'bold' ], '3' );
		$this->assertEquals( "mn", $mn->getName() );
		$this->assertSame( "3", $mn->getText() );
	}
}
