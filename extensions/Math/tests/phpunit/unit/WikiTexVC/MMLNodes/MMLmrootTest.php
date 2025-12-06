<?php

namespace MediaWiki\Extension\Math\Tests\WikiTexVC\MMLNodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\TexConstants\Variants;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmi;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmn;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmroot;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmroot
 *
 * @group Math
 *
 * @license GPL-2.0-or-later
 */
class MMLmrootTest extends MediaWikiUnitTestCase {
	public function testConstructor() {
		$mroot = new MMLmroot( '', [ 'mathvariant' => Variants::BOLD ] );
		$this->assertEquals( 'mroot', $mroot->getName() );
		$this->assertEquals( [ 'mathvariant' => Variants::BOLD ], $mroot->getAttributes() );
	}

	public function testTreeConstructor() {
		$mi = new MMLmi( '', [], 'x' );
		$mn = new MMLmn( '', [], '5' );
		$mroot = MMLmroot::newSubtree( $mi, $mn, '', [ 'mathvariant' => Variants::BOLD ] );
		$this->assertEquals( 'mroot', $mroot->getName() );
		$this->assertEquals( [ 'mathvariant' => Variants::BOLD ], $mroot->getAttributes() );
		$this->assertEquals( $mroot->getChildren(), [ $mi, $mn ] );
	}
}
