<?php

namespace MediaWiki\Tests\OutputTransform;

use MediaWiki\Context\RequestContext;
use MediaWiki\MainConfigNames;
use MediaWiki\OutputTransform\OutputTransformStage;
use MediaWiki\Parser\Parsoid\PageBundleParserOutputConverter;
use MediaWikiIntegrationTestCase;

abstract class OutputTransformStageTestBase extends MediaWikiIntegrationTestCase {
	abstract public function createStage(): OutputTransformStage;

	abstract public function provideShouldRun(): iterable;

	abstract public function provideShouldNotRun(): iterable;

	abstract public function provideTransform(): iterable;

	/**
	 * @dataProvider provideShouldRun
	 */
	public function testShouldRun( $parserOutput, $parserOptions, $options ) {
		$stage = $this->createStage();
		$this->assertTrue( $stage->shouldRun( $parserOutput, $parserOptions, $options ) );
	}

	public function setUp(): void {
		RequestContext::resetMain();
		$this->overrideConfigValues( [
			MainConfigNames::ArticlePath => '/wiki/$1',
			MainConfigNames::ScriptPath => '/w',
			MainConfigNames::Script => '/w/index.php',
			MainConfigNames::Server => '//TEST_SERVER',
			MainConfigNames::DefaultSkin => 'fallback'
		] );
	}

	/**
	 * @dataProvider provideShouldNotRun
	 */
	public function testShouldNotRun( $parserOutput, $parserOptions, $options ) {
		$stage = $this->createStage();
		$this->assertFalse( $stage->shouldRun( $parserOutput, $parserOptions, $options ) );
	}

	/**
	 * @dataProvider provideTransform
	 */
	public function testTransform( $parserOutput, $parserOptions, $options, $expected ) {
		$stage = $this->createStage();
		$result = $stage->transform( $parserOutput, $parserOptions, $options );
		// If this has Parsoid internal metadata, clear it in both the expected
		// value and the result; these are internal implementation details
		// that shouldn't be hardwired into tests.
		if ( PageBundleParserOutputConverter::hasPageBundle( $result ) ) {
			$key = PageBundleParserOutputConverter::PARSOID_PAGE_BUNDLE_KEY;
			$expected->setExtensionData( $key, $result->getExtensionData( $key ) );
		}
		$this->assertEquals( $expected, $result );
	}
}
