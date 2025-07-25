<?php

namespace phpunit\unit\WikiTexVC\MMLNodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmi;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmn;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmunder;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmunder
 *
 * @group Math
 *
 * @license GPL-2.0-or-later
 */
class MMLmunderTest extends MediaWikiUnitTestCase {
	public function testConstructor() {
		$mover = new MMLmunder( '', [ 'mathvariant' => 'bold' ] );
		$this->assertEquals( 'munder', $mover->getName() );
		$this->assertEquals( [ 'mathvariant' => 'bold' ], $mover->getAttributes() );
	}

	public function testTreeConstructor() {
		$mi = new MMLmi( '', [], 'x' );
		$mn = new MMLmn( '', [], '5' );
		$mover = MMLmunder::newSubtree( $mi, $mn, '', [ 'mathvariant' => 'bold' ] );
		$this->assertEquals( 'munder', $mover->getName() );
		$this->assertEquals( [ 'mathvariant' => 'bold' ], $mover->getAttributes() );
		$this->assertEquals( $mover->getChildren(), [ $mi, $mn ] );
	}
}
