<?php

namespace MediaWiki\Extension\Math\Tests\WikiTexVC\MMLnodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmenclose;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmenclose
 *
 * @group Math
 *
 * @license GPL-2.0-or-later
 */
class MMLmencloseTest extends MediaWikiUnitTestCase {
	public function testConstructor() {
		$mn = new MMLmenclose();
		$this->assertEquals( "menclose", $mn->getName() );
	}
}
