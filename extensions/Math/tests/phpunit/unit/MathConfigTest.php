<?php

namespace MediaWiki\Extension\Math\Tests;

use ExtensionRegistry;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\Math\MathConfig;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\Math\MathConfig
 */
class MathConfigTest extends TestCase {

	private const TEST_DEFAULT = 'test-default';
	private const DUMMY_URL = 'https://example.com/api.php';

	private function newMathConfig(
		array $configOverrides,
		ExtensionRegistry $registry = null
	): MathConfig {
		if ( $registry === null ) {
			$registry = ExtensionRegistry::getInstance();
		}
		return new MathConfig(
			new ServiceOptions( MathConfig::CONSTRUCTOR_OPTIONS, $configOverrides + [
				'MathDisableTexFilter' => MathConfig::ALWAYS,
				'MathValidModes' => [ MathConfig::MODE_SOURCE ],
				'MathEntitySelectorFallbackUrl' => self::DUMMY_URL,
			] ),
			$registry
		);
	}

	public function provideTexCheckDisabled() {
		yield 'always' => [ 'always', MathConfig::ALWAYS ];
		yield 'never' => [ 'never', MathConfig::NEVER ];
		yield 'new' => [ 'new', MathConfig::NEW ];
		yield 'true' => [ true, MathConfig::NEVER ];
		yield 'false' => [ false, MathConfig::ALWAYS ];
		yield 'garbage' => [ 'garbage', MathConfig::ALWAYS ];
		yield 'wrong case' => [ 'NEVER', MathConfig::NEVER ];
	}

	/**
	 * @dataProvider provideTexCheckDisabled
	 */
	public function testTexCheckDisabled( $configValue, string $expected ) {
		$mathConfig = $this->newMathConfig( [ 'MathDisableTexFilter' => $configValue ] );
		$this->assertSame( $expected, $mathConfig->texCheckDisabled() );
	}

	public function provideNormalizeRenderingMode() {
		yield 'legacy user option' => [ 1, self::TEST_DEFAULT ];
		yield 'png user option' => [ 0, MathConfig::MODE_PNG ];
		yield 'source user option' => [ 3, MathConfig::MODE_SOURCE ];
		yield 'mathml user option' => [ 5, MathConfig::MODE_MATHML ];
		yield 'latexml user option' => [ 7, MathConfig::MODE_LATEXML ];
		yield 'png string' => [ 'png', MathConfig::MODE_PNG ];
		yield 'source string' => [ 'source', MathConfig::MODE_SOURCE ];
		yield 'mathml string' => [ 'mathml', MathConfig::MODE_MATHML ];
		yield 'latexml string' => [ 'latexml', MathConfig::MODE_LATEXML ];
		yield 'wrong capitalizaton' => [ 'LaTeXmL', MathConfig::MODE_LATEXML ];
		yield 'unrecognized' => [ 'garbage', self::TEST_DEFAULT ];
	}

	/**
	 * @dataProvider provideNormalizeRenderingMode
	 */
	public function testNormalizeRenderingMode( $input, string $expected ) {
		$this->assertSame( $expected, MathConfig::normalizeRenderingMode( $input, self::TEST_DEFAULT ) );
	}

	public function testGetValidRenderingModes() {
		$mathConfig = $this->newMathConfig( [
			'MathValidModes' => [ MathConfig::MODE_MATHML, 5, MathConfig::MODE_PNG, 'this will be converted to png', ],
		] );
		$actualModes = $mathConfig->getValidRenderingModes();
		$this->assertCount( 2, $actualModes );
		$this->assertContains( MathConfig::MODE_MATHML, $actualModes );
		$this->assertContains( MathConfig::MODE_PNG, $actualModes );
	}

	public function provideIsValidRenderingMode() {
		yield 'valid' => [ MathConfig::MODE_MATHML, true ];
		yield 'garbage' => [ 'garbage', false ];
		yield 'does not normalize' => [ 0, false ];
	}

	/**
	 * @dataProvider provideIsValidRenderingMode
	 */
	public function testIsValidRenderingMode( $mode, $expected ) {
		$mathConfig = $this->newMathConfig( [
			'MathValidModes' => [ MathConfig::MODE_PNG, MathConfig::MODE_MATHML ],
		] );
		$this->assertSame( $expected, $mathConfig->isValidRenderingMode( $mode ) );
	}

	public function testGetValidRenderingModeKeys() {
		$mathConfig = $this->newMathConfig( [
			'MathValidModes' => [ MathConfig::MODE_PNG ],
		] );
		$this->assertEquals(
			[ 'png' => 'mw_math_png' ],
			$mathConfig->getValidRenderingModeKeys() );
	}

	public function testEntitySelectorUrlWikibase() {
		$mathConfig = $this->newMathConfig( [] );
		if ( ExtensionRegistry::getInstance()->isLoaded( 'WikibaseClient' ) ) {
			$localUrl = $mathConfig->getMathEntitySelectorUrl();
			$this->assertStringContainsString( 'api.php', $localUrl );
			$this->assertStringNotContainsString( 'example.com', $localUrl );
		} else {
			$this->markTestSkipped( 'Wikibase Client is not loaded partially skipping test.' );
		}
	}

	public function testEntitySelectorUrlFallback() {
		$mathConfig = $this->newMathConfig( [], new class extends ExtensionRegistry {
			public function isLoaded( $name, $constraint = '*' ): bool {
				return false;
			}
		} );
		$url = $mathConfig->getMathEntitySelectorUrl();
		$this->assertEquals( self::DUMMY_URL, $url );
	}

}
