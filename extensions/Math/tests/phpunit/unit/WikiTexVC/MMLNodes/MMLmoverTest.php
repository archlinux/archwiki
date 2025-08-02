<?php

namespace phpunit\unit\WikiTexVC\MMLNodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmi;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmn;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmover;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmover
 *
 * @group Math
 *
 * @license GPL-2.0-or-later
 */
class MMLmoverTest extends MediaWikiUnitTestCase {
	public function testConstructor() {
		$mover = new MMLmover( '', [ 'mathvariant' => 'bold' ] );
		$this->assertEquals( 'mover', $mover->getName() );
		$this->assertEquals( [ 'mathvariant' => 'bold' ], $mover->getAttributes() );
	}

	public function testTreeConstructor() {
		$mi = new MMLmi( '', [], 'x' );
		$mn = new MMLmn( '', [], '5' );
		$mover = MMLmover::newSubtree( $mi, $mn, '', [ 'mathvariant' => 'bold' ] );
		$this->assertEquals( 'mover', $mover->getName() );
		$this->assertEquals( [ 'mathvariant' => 'bold' ], $mover->getAttributes() );
		$this->assertEquals( $mover->getChildren(), [ $mi, $mn ] );
	}
}
