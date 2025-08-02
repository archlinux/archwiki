<?php

namespace phpunit\unit\WikiTexVC\MMLNodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmfrac;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmi;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmn;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmo;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmrow;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmfrac
 *
 * @group Math
 *
 * @license GPL-2.0-or-later
 */
class MMLmfracTest extends MediaWikiUnitTestCase {
	public function testConstructor() {
		$frac = new MMLmfrac( '', [ 'mathvariant' => 'bold' ] );
		$this->assertEquals( 'mfrac', $frac->getName() );
		$this->assertEquals( [ 'mathvariant' => 'bold' ], $frac->getAttributes() );
	}

	public function testTreeConstructor() {
		$mi = new MMLmi( '', [], 'x' );
		$mo = new MMLmo( '', [], '+' );
		$mn = new MMLmn( '', [], '5' );
		$mrowNumerator = new MMLmrow( '', [ 'mathvariant' => 'bold' ], $mi, $mo, $mn );
		$mrowDenominator = new MMLmrow( '', [ 'mathvariant' => 'bold' ], $mn, $mo, $mi );
		$frac = MMLmfrac::newSubtree( $mrowNumerator, $mrowDenominator, '', [ 'mathvariant' => 'bold' ] );
		$this->assertEquals( 'mfrac', $frac->getName() );
		$this->assertEquals( [ 'mathvariant' => 'bold' ], $frac->getAttributes() );
		$this->assertEquals( $frac->getChildren(), [ $mrowNumerator, $mrowDenominator ] );
	}
}
