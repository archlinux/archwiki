<?php

namespace MediaWiki\Extension\Math\Tests\WikiTexVC\MMLnodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmo;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmo
 *
 * @group Math
 *
 * @license GPL-2.0-or-later
 */
class MMLmoTest extends MediaWikiUnitTestCase {
	public function testConstructor() {
		$mo = new MMLmo( '', [ 'mathvariant' => 'bold' ], '+' );
		$this->assertEquals( "mo", $mo->getName() );
		$this->assertEquals( "+", $mo->getText() );
	}
}
