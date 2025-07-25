<?php

namespace phpunit\unit\WikiTexVC\MMLNodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmi;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmn;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmo;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmphantom;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmphantom
 *
 * @group Math
 *
 * @license GPL-2.0-or-later
 */
class MMLmphantomTest extends MediaWikiUnitTestCase {
	public function testConstructor() {
		$mi = new MMLmi( '', [], 'x' );
		$mo = new MMLmo( '', [], '+' );
		$mn = new MMLmn( '', [], '5' );
		$mphantom = new MMLmphantom( '', [ 'mathvariant' => 'bold' ], $mi, $mo, $mn );

		$this->assertEquals( 'mphantom', $mphantom->getName() );
		$this->assertEquals( [ 'mathvariant' => 'bold' ], $mphantom->getAttributes() );
		$this->assertEquals( $mphantom->getChildren(), [ $mi, $mo, $mn ] );
	}
}
