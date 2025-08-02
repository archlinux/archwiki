<?php

namespace phpunit\unit\WikiTexVC\MMLNodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmi;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmn;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmo;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmunderover;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmunderover
 *
 * @group Math
 *
 * @license GPL-2.0-or-later
 */
class MMLmunderoverTest extends MediaWikiUnitTestCase {
	public function testConstructor() {
		$munderover = new MMLmunderover( '', [ 'mathvariant' => 'bold' ] );

		$this->assertEquals( 'munderover', $munderover->getName() );
		$this->assertEquals( [ 'mathvariant' => 'bold' ], $munderover->getAttributes() );
	}

	public function testTreeConstructor() {
		$mo = new MMLmo( '', [], '∑' );
		$mi = new MMLmi( '', [], 'x' );
		$mn = new MMLmn( '', [], '5' );
		$munderover = MMLmunderover::newSubtree( $mo, $mi, $mn, '', [ 'mathvariant' => 'bold' ] );

		$this->assertEquals( 'munderover', $munderover->getName() );
		$this->assertEquals( [ 'mathvariant' => 'bold' ], $munderover->getAttributes() );
		$this->assertEquals( $munderover->getChildren(), [ $mo, $mi, $mn ] );
	}
}
