<?php

namespace MediaWiki\Extension\Math\Tests\WikiTexVC\MMLNodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\TexConstants\Variants;
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
		$msqrt = new MMLmsqrt( '', [ 'mathvariant' => Variants::BOLD ], $mn );

		$this->assertEquals( 'msqrt', $msqrt->getName() );
		$this->assertEquals( [ 'mathvariant' => Variants::BOLD ], $msqrt->getAttributes() );
		$this->assertEquals( $msqrt->getChildren(), [ $mn ] );
	}
}
