<?php

namespace MediaWiki\Extension\Math\Tests\WikiTexVC\MMLNodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\TexConstants\Variants;
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
		$frac = new MMLmfrac( '', [ 'mathvariant' => Variants::BOLD ] );
		$this->assertEquals( 'mfrac', $frac->getName() );
		$this->assertEquals( [ 'mathvariant' => Variants::BOLD ], $frac->getAttributes() );
	}

	public function testTreeConstructor() {
		$mi = new MMLmi( '', [], 'x' );
		$mo = new MMLmo( '', [], '+' );
		$mn = new MMLmn( '', [], '5' );
		$mrowNumerator = new MMLmrow( '', [ 'mathvariant' => Variants::BOLD ], $mi, $mo, $mn );
		$mrowDenominator = new MMLmrow( '', [ 'mathvariant' => Variants::BOLD ], $mn, $mo, $mi );
		$frac = MMLmfrac::newSubtree( $mrowNumerator, $mrowDenominator, '', [ 'mathvariant' => Variants::BOLD ] );
		$this->assertEquals( 'mfrac', $frac->getName() );
		$this->assertEquals( [ 'mathvariant' => Variants::BOLD ], $frac->getAttributes() );
		$this->assertEquals( $frac->getChildren(), [ $mrowNumerator, $mrowDenominator ] );
	}
}
