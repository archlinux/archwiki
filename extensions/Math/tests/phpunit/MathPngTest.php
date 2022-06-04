<?php

use MediaWiki\Extension\Math\MathConfig;
use MediaWiki\Extension\Math\MathPng;

/**
 * @covers \MediaWiki\Extension\Math\MathPng
 *
 * @license GPL-2.0-or-later
 */
class MathPngTest extends MediaWikiIntegrationTestCase {

	/** @var string The fallback image HTML tag */
	private const TEST_DUMMY = '<img src="test.png" />';

	public function testConstructor() {
		$renderer = new MathPng( 'a' );

		$this->assertEquals( MathConfig::MODE_PNG, $renderer->getMode() );
	}

	public function testOutput() {
		$renderer = $this->getMockBuilder( MathPng::class )
			->onlyMethods( [ 'getFallbackImage' ] )
			->getMock();
		$renderer->method( 'getFallbackImage' )
			->willReturn( self::TEST_DUMMY );

		$this->assertSame( self::TEST_DUMMY, $renderer->getHtmlOutput() );
	}

}
