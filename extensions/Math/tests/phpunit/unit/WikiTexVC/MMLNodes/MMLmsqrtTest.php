<?php

namespace phpunit\unit\WikiTexVC\MMLNodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmn;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmsqrt;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmsqrt
 *
 * @group Math
 *
 * @license GPL-2.0-or-later
 */
class MMLmsqrtTest extends MediaWikiUnitTestCase {
	public function testConstructor() {
		$mn = new MMLmn( '', [], '5' );
		$msqrt = new MMLmsqrt( '', [ 'mathvariant' => 'bold' ], $mn );

		$this->assertEquals( 'msqrt', $msqrt->getName() );
		$this->assertEquals( [ 'mathvariant' => 'bold' ], $msqrt->getAttributes() );
		$this->assertEquals( $msqrt->getChildren(), [ $mn ] );
	}
}
