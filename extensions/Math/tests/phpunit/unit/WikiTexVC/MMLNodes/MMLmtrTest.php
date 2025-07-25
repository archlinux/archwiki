<?php

namespace phpunit\unit\WikiTexVC\MMLNodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmtd;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmtr;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmtr
 *
 * @group Math
 *
 * @license GPL-2.0-or-later
 */
class MMLmtrTest extends MediaWikiUnitTestCase {
	public function testConstructor() {
		$mtd = new MMLmtd( '', [] );
		$mtr = new MMLmtr( '', [ 'mathvariant' => 'bold' ], $mtd, $mtd );

		$this->assertEquals( 'mtr', $mtr->getName() );
		$this->assertEquals( [ 'mathvariant' => 'bold' ], $mtr->getAttributes() );
		$this->assertEquals( $mtr->getChildren(), [ $mtd, $mtd ] );
	}
}
