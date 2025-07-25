<?php

namespace phpunit\unit\WikiTexVC\MMLNodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmi;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmn;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmo;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmstyle;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmstyle
 *
 * @group Math
 *
 * @license GPL-2.0-or-later
 */
class MMLmstyleTest extends MediaWikiUnitTestCase {
	public function testConstructor() {
		$mi = new MMLmi( '', [], 'x' );
		$mo = new MMLmo( '', [], '+' );
		$mn = new MMLmn( '', [], '5' );
		$mstyle = new MMLmstyle( '', [ 'mathvariant' => 'bold' ], $mi, $mo, $mn );

		$this->assertEquals( 'mstyle', $mstyle->getName() );
		$this->assertEquals( [ 'mathvariant' => 'bold' ], $mstyle->getAttributes() );
		$this->assertEquals( $mstyle->getChildren(), [ $mi, $mo, $mn ] );
	}
}
