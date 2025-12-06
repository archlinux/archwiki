<?php

namespace MediaWiki\Extension\Math\Tests\WikiTexVC\MMLNodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\TexConstants\Variants;
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
		$munderover = new MMLmunderover( '', [ 'mathvariant' => Variants::BOLD ] );

		$this->assertEquals( 'munderover', $munderover->getName() );
		$this->assertEquals( [ 'mathvariant' => Variants::BOLD ], $munderover->getAttributes() );
	}

	public function testTreeConstructor() {
		$mo = new MMLmo( '', [], '∑' );
		$mi = new MMLmi( '', [], 'x' );
		$mn = new MMLmn( '', [], '5' );
		$munderover = MMLmunderover::newSubtree( $mo, $mi, $mn, '', [ 'mathvariant' => Variants::BOLD ] );

		$this->assertEquals( 'munderover', $munderover->getName() );
		$this->assertEquals( [ 'mathvariant' => Variants::BOLD ], $munderover->getAttributes() );
		$this->assertEquals( $munderover->getChildren(), [ $mo, $mi, $mn ] );
	}
}
