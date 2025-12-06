<?php

namespace MediaWiki\Extension\Math\Tests\WikiTexVC\MMLNodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\TexConstants\Variants;
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
		$mer = new MMLmerror( '', [ 'mathvariant' => Variants::BOLD ], $mt );

		$this->assertEquals( 'merror', $mer->getName() );
		$this->assertEquals( [ 'mathvariant' => Variants::BOLD ], $mer->getAttributes() );
		$this->assertEquals( $mer->getChildren(), [ $mt ] );
	}
}
