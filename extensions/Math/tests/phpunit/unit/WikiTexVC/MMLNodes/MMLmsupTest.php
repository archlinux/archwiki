<?php

namespace phpunit\unit\WikiTexVC\MMLNodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmi;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmn;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmsup;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmsup
 *
 * @group Math
 *
 * @license GPL-2.0-or-later
 */
class MMLmsupTest extends MediaWikiUnitTestCase {
	public function testConstructor() {
		$msup = new MMLmsup( '', [ 'mathvariant' => 'bold' ] );
		$this->assertEquals( 'msup', $msup->getName() );
		$this->assertEquals( [ 'mathvariant' => 'bold' ], $msup->getAttributes() );
	}

	public function testTreeConstructor() {
		$mi = new MMLmi( '', [], 'x' );
		$mn = new MMLmn( '', [], '5' );
		$msup = MMLmsup::newSubtree( $mi, $mn, '', [ 'mathvariant' => 'bold' ] );

		$this->assertEquals( 'msup', $msup->getName() );
		$this->assertEquals( [ 'mathvariant' => 'bold' ], $msup->getAttributes() );
		$this->assertEquals( $msup->getChildren(), [ $mi, $mn ] );
	}
}
