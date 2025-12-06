<?php

namespace MediaWiki\Extension\Math\Tests\WikiTexVC\MMLNodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\TexConstants\Variants;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmi;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmn;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmsub;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmsub
 *
 * @group Math
 *
 * @license GPL-2.0-or-later
 */
class MMLmsubTest extends MediaWikiUnitTestCase {
	public function testConstructor() {
		$msub = new MMLmsub( '', [ 'mathvariant' => Variants::BOLD ] );
		$this->assertEquals( 'msub', $msub->getName() );
		$this->assertEquals( [ 'mathvariant' => Variants::BOLD ], $msub->getAttributes() );
	}

	public function testTreeConstructor() {
		$mi = new MMLmi( '', [], 'x' );
		$mn = new MMLmn( '', [], '5' );
		$msub = MMLmsub::newSubtree( $mi, $mn, '', [ 'mathvariant' => Variants::BOLD ] );
		$this->assertEquals( 'msub', $msub->getName() );
		$this->assertEquals( [ 'mathvariant' => Variants::BOLD ], $msub->getAttributes() );
		$this->assertEquals( $msub->getChildren(), [ $mi, $mn ] );
	}
}
