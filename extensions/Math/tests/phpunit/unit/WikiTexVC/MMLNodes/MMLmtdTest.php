<?php

namespace MediaWiki\Extension\Math\Tests\WikiTexVC\MMLNodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\TexConstants\Variants;
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
		$mtd = new MMLmtd( '', [ 'mathvariant' => Variants::BOLD ], $mi );

		$this->assertEquals( 'mtd', $mtd->getName() );
		$this->assertEquals( [ 'mathvariant' => Variants::BOLD ], $mtd->getAttributes() );
		$this->assertEquals( $mtd->getChildren(), [ $mi ] );
	}
}
