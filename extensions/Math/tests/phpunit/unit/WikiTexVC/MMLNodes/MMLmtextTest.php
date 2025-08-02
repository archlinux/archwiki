<?php

namespace MediaWiki\Extension\Math\Tests\WikiTexVC\MMLnodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmtext;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmtext
 *
 * @group Math
 *
 * @license GPL-2.0-or-later
 */
class MMLmtextTest extends MediaWikiUnitTestCase {
	public function testConstructor() {
		$mtext = new MMLmtext( '', [ 'mathvariant' => 'bold' ], 'test' );
		$this->assertEquals( "mtext", $mtext->getName() );
		$this->assertEquals( "test", $mtext->getText() );
	}
}
