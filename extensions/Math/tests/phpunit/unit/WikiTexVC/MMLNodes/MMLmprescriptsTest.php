<?php

namespace phpunit\unit\WikiTexVC\MMLNodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmprescripts;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmprescripts
 *
 * @group Math
 *
 * @license GPL-2.0-or-later
 */
class MMLmprescriptsTest extends MediaWikiUnitTestCase {
	public function testConstructor() {
		$mprescripts = new MMLmprescripts();
		$this->assertEquals( 'mprescripts', $mprescripts->getName() );
		$this->assertEquals( [], $mprescripts->getAttributes() );
		$this->assertEquals( '<mprescripts/>', (string)$mprescripts );
	}
}
