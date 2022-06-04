<?php

use MediaWiki\Extension\Math\MathRestbaseInterface;
use MediaWiki\Extension\Math\Tests\MathMockHttpTrait;

/**
 * Test the interface to access Restbase paths
 * /media/math/check/{type}
 * /media/math/render/{format}/{hash}
 *
 * @covers \MediaWiki\Extension\Math\MathRestbaseInterface
 *
 * @group Math
 *
 * @license GPL-2.0-or-later
 */
class MathRestbaseInterfaceTest extends MediaWikiIntegrationTestCase {
	use MathMockHttpTrait;

	public function testSuccess() {
		$this->setupGoodMathRestBaseMockHttp();

		$input = '\\sin x^2';
		$rbi = new MathRestbaseInterface( $input );
		$this->assertTrue( $rbi->getSuccess(), "Assuming that $input is valid input." );
		$this->assertEquals( '\\sin x^{2}', $rbi->getCheckedTex() );
		$this->assertStringContainsString( '<mi>sin</mi>', $rbi->getMathML() );
		$this->assertStringContainsString( '/svg/RESOURCE_LOCATION', $rbi->getFullSvgUrl() );
		$this->assertStringContainsString( '/png/RESOURCE_LOCATION', $rbi->getFullPngUrl() );
	}

	public function testFail() {
		$this->setupBadMathRestBaseMockHttp();

		$input = '\\sin\\newcommand';
		$rbi = new MathRestbaseInterface( $input );
		$this->assertFalse( $rbi->getSuccess(), "Assuming that $input is invalid input." );
		$this->assertNull( $rbi->getCheckedTex() );
		$this->assertEquals( 'Illegal TeX function', $rbi->getError()->error->message );
	}

	public function testChem() {
		$this->setupGoodChemRestBaseMockHttp();

		$input = '\ce{H2O}';
		$rbi = new MathRestbaseInterface( $input, 'chem' );
		$this->assertTrue( $rbi->checkTeX(), "Assuming that $input is valid input." );
		$this->assertTrue( $rbi->getSuccess(), "Assuming that $input is valid input." );
		$this->assertEquals( '{\ce {H2O}}', $rbi->getCheckedTex() );
		$this->assertStringContainsString( '<msubsup>', $rbi->getMathML() );
		$this->assertStringContainsString( '<mtext>H</mtext>', $rbi->getMathML() );
	}

	public function testException() {
		$this->setupBadMathRestBaseMockHttp();

		$input = '\\sin\\newcommand';
		$rbi = new MathRestbaseInterface( $input );
		$this->expectException( MWException::class );
		$this->expectExceptionMessage( 'TeX input is invalid.' );
		$rbi->getMathML();
	}

	public function testExceptionSvg() {
		$this->setupBadMathRestBaseMockHttp();

		$input = '\\sin\\newcommand';
		$rbi = new MathRestbaseInterface( $input );
		$this->expectException( MWException::class );
		$this->expectExceptionMessage( 'TeX input is invalid.' );
		$rbi->getFullSvgUrl();
	}

	/**
	 * Incorporate the "details" in the error message, if the check requests passes, but the
	 * mml/svg/complete endpoints returns an error
	 */
	public function testLateError() {
		// phpcs:ignore Generic.Files.LineLength.TooLong
		$input = '{"type":"https://mediawiki.org/wiki/HyperSwitch/errors/bad_request","title":"Bad Request","method":"POST","detail":["TeX parse error: Missing close brace"],"uri":"/complete"}';
		$this->expectException( MWException::class );
		$this->expectExceptionMessage( 'Cannot get mml. TeX parse error: Missing close brace' );
		MathRestbaseInterface::throwContentError( 'mml', $input );
	}

	/**
	 * Incorporate the "details" in the error message, if the check requests passes, but the
	 * mml/svg/complete endpoints returns an error
	 */
	public function testLateErrorString() {
		// phpcs:ignore Generic.Files.LineLength.TooLong
		$input = '{"type":"https://mediawiki.org/wiki/HyperSwitch/errors/bad_request","title":"Bad Request","method":"POST","detail": "TeX parse error: Missing close brace","uri":"/complete"}';
		$this->expectException( MWException::class );
		$this->expectExceptionMessage( 'Cannot get mml. TeX parse error: Missing close brace' );
		MathRestbaseInterface::throwContentError( 'mml', $input );
	}

	public function testLateErrorNoDetail() {
		$input = '';
		$this->expectException( MWException::class );
		$this->expectExceptionMessage( 'Cannot get mml. Server problem.' );
		MathRestbaseInterface::throwContentError( 'mml', $input );
	}
}
