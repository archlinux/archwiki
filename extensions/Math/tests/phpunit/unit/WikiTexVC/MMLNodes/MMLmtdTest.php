<?php

namespace phpunit\unit\WikiTexVC\MMLNodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmi;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmtd;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmtd
 *
 * @group Math
 *
 * @license GPL-2.0-or-later
 */
class MMLmtdTest extends MediaWikiUnitTestCase {
	public function testConstructor() {
		$mi = new MMLmi( '', [], 'x' );
		$mtd = new MMLmtd( '', [ 'mathvariant' => 'bold' ], $mi );

		$this->assertEquals( 'mtd', $mtd->getName() );
		$this->assertEquals( [ 'mathvariant' => 'bold' ], $mtd->getAttributes() );
		$this->assertEquals( $mtd->getChildren(), [ $mi ] );
	}
}
