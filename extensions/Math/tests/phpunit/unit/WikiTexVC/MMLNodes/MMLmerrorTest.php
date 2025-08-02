<?php

namespace phpunit\unit\WikiTexVC\MMLNodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmerror;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmtext;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmerror
 *
 * @group Math
 *
 * @license GPL-2.0-or-later
 */
class MMLmerrorTest extends MediaWikiUnitTestCase {
	public function testConstructor() {
		$mt = new MMLmtext( '', [], 'Error' );
		$mer = new MMLmerror( '', [ 'mathvariant' => 'bold' ], $mt );

		$this->assertEquals( 'merror', $mer->getName() );
		$this->assertEquals( [ 'mathvariant' => 'bold' ], $mer->getAttributes() );
		$this->assertEquals( $mer->getChildren(), [ $mt ] );
	}
}
