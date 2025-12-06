<?php

namespace MediaWiki\Extension\Math\Tests\WikiTexVC\MMLNodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\TexConstants\Variants;
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
		$mover = new MMLmover( '', [ 'mathvariant' => Variants::BOLD ] );
		$this->assertEquals( 'mover', $mover->getName() );
		$this->assertEquals( [ 'mathvariant' => Variants::BOLD ], $mover->getAttributes() );
	}

	public function testTreeConstructor() {
		$mi = new MMLmi( '', [], 'x' );
		$mn = new MMLmn( '', [], '5' );
		$mover = MMLmover::newSubtree( $mi, $mn, '', [ 'mathvariant' => Variants::BOLD ] );
		$this->assertEquals( 'mover', $mover->getName() );
		$this->assertEquals( [ 'mathvariant' => Variants::BOLD ], $mover->getAttributes() );
		$this->assertEquals( $mover->getChildren(), [ $mi, $mn ] );
	}
}
