<?php

use MediaWiki\Extension\Math\MathNativeMML;
use MediaWiki\Extension\Math\MathWikibaseConnector;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\LBFactory;

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
		$db = $this->createMock( IDatabase::class );
		$db->method( 'selectRow' )->willReturn( false );
		$lbFactory = $this->createMock( LBFactory::class );
		$lbFactory->method( 'getReplicaDatabase' )->willReturn( $db );
		$this->setService( 'DBLoadBalancerFactory', $lbFactory );
		$this->overrideConfigValue( 'MathValidModes', [ 'native' ] );
		$this->clearHooks();
	}

	public function testSin() {
		$mml = new MathNativeMML( '\sin' );
		$this->assertSame( 'tex', $mml->getInputType() );
		$this->assertTrue( $mml->checkTeX() );
		$this->assertTrue( $mml->render() );
		$this->assertStringContainsString( 'sin', $mml->getMathml() );
	}

	public function testNoLink() {
		$this->overrideConfigValue( 'MathEnableFormulaLinks', false );
		$mml = new MathNativeMML( '\sin', [ 'qid' => 'Q1' ] );
		$this->assertTrue( $mml->render() );
		$this->assertStringNotContainsString( 'href', $mml->getMathml() );
	}

	public function testLink() {
		$db = $this->createMock( MathWikibaseConnector::class );
		$db->method( 'getUrlFromSymbol' )->willReturn( [ 'E' =>
			[ 'url' => 'https://example.com/', 'title' => 'Energy' ] ] );
		$this->setService( 'Math.WikibaseConnector', $db );
		$this->overrideConfigValue( 'MathEnableFormulaLinks', true );
		$mml = new MathNativeMML( 'E=mc', [ 'qid' => 'Q1' ] );
		$this->assertTrue( $mml->render() );
		$this->assertStringContainsString( 'href', $mml->getMathml() );
	}

	public function testEmptyLink() {
		$db = $this->createMock( MathWikibaseConnector::class );
		$db->method( 'getUrlFromSymbol' )->willReturn( [ 'E' => '' ] );
		$this->setService( 'Math.WikibaseConnector', $db );
		$this->overrideConfigValue( 'MathEnableFormulaLinks', true );
		$mml = new MathNativeMML( 'E=mc', [ 'qid' => 'Q1' ] );
		$this->assertTrue( $mml->render() );
		$this->assertStringNotContainsString( 'href', $mml->getMathml() );
	}

	public function testDifferentLinks() {
		$db = $this->createMock( MathWikibaseConnector::class );
		$db->method( 'getUrlFromSymbol' )->willReturn( [
			'+' => [ 'url' => 'https://example.com/plus', 'title' => 'Plus' ],
			'hello' => [ 'url' => 'https://example.com/hello', 'title' => 'Hello' ],
		] );
		$this->setService( 'Math.WikibaseConnector', $db );
		$this->overrideConfigValue( 'MathEnableFormulaLinks', true );
		$mml = new MathNativeMML( 'a+\text{hello}', [ 'qid' => "Q1" ] );
		$this->assertTrue( $mml->render() );
		$mathml = $mml->getMathml();
		$this->assertStringContainsString(
			'<mrow href="https://example.com/plus" title="Plus"><mo stretchy="false">+</mo></mrow>', $mathml
		);
		$this->assertStringContainsString(
			'<mrow href="https://example.com/hello" title="Hello"><mtext>hello</mtext></mrow>', $mathml
		);
	}

	public function testMultipleOccurrencesAreAllWrapped() {
		$db = $this->createMock( MathWikibaseConnector::class );
		$db->method( 'getUrlFromSymbol' )->willReturn( [
			'E' => [ 'url' => 'https://example.com/E', 'title' => 'Energy' ],
		] );
		$this->setService( 'Math.WikibaseConnector', $db );
		$this->overrideConfigValue( 'MathEnableFormulaLinks', true );
		$mml = new MathNativeMML( 'E+E', [ 'qid' => "Q1" ] );
		$this->assertTrue( $mml->render() );
		$mathml = $mml->getMathml();
		// should be 2 wrappers.
		$this->assertSame( 2, substr_count( $mathml, 'href="https://example.com/E"' ) );
	}

	public function testId() {
		$mml = new MathNativeMML( '\sin', [ 'id' => 'unique-id' ] );
		$this->assertTrue( $mml->render() );
		$this->assertStringContainsString( 'unique-id', $mml->getMathml() );
	}

	public function testBlock() {
		$mml = new MathNativeMML( '\sin', [ 'display' => 'block' ] );
		$this->assertTrue( $mml->render() );
		$this->assertStringContainsString( 'block', $mml->getMathml() );
	}

	public function testGetHtmlNoMathJax() {
		$math = new MathNativeMML( "a+b", [ 'class' => 'mathjax_ignore' ] );
		$math->render();
		$out = $math->getHtmlOutput( false );
		$this->assertStringContainsString( 'mathjax_ignore', $out );
	}
}
