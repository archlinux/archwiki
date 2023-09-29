<?php

use MediaWiki\Extension\Math\MathNativeMML;

/**
 * Test the native MathML output format.
 *
 * @covers \MediaWiki\Extension\Math\MathNativeMML
 *
 * @group Math
 *
 * @license GPL-2.0-or-later
 */
class MathNativeMMLTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->setMwGlobals( 'wgMathValidModes', [ 'native' ] );
	}

	public function testSin() {
		$mml = new MathNativeMML( '\sin' );
		$this->assertSame( 'tex', $mml->getInputType() );
		$this->assertTrue( $mml->checkTeX() );
		$this->assertTrue( $mml->render() );
		$this->assertStringContainsString( 'sin', $mml->getMathml() );
	}

}
