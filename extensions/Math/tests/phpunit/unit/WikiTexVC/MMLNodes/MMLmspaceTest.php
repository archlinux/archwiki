<?php

namespace MediaWiki\Extension\Math\Tests\WikiTexVC\MMLnodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmspace;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmspace
 *
 * @group Math
 *
 * @license GPL-2.0-or-later
 */
class MMLmspaceTest extends MediaWikiUnitTestCase {
	public function testConstructor() {
		$mn = new MMLmspace();
		$this->assertEquals( "mspace", $mn->getName() );
	}
}
