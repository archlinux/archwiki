<?php

namespace phpunit\unit\WikiTexVC\MMLNodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmi;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmtable;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmtd;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmtr;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmtable
 *
 * @group Math
 *
 * @license GPL-2.0-or-later
 */
class MMLmtableTest extends MediaWikiUnitTestCase {
	public function testConstructor() {
		$mi = new MMLmi( '', [], '5' );
		$mtd = new MMLmtd( '', [], $mi );
		$mtr = new MMLmtr( '', [], $mtd );
		$table = new MMLmtable( '', [], $mtr, $mtr );

		$this->assertEquals( 'mtable', $table->getName() );
		$this->assertEquals( [], $table->getAttributes() );
		$this->assertEquals( $table->getChildren(), [ $mtr, $mtr ] );
	}
}
