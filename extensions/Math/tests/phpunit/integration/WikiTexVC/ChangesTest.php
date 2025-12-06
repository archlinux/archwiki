<?php
namespace MediaWiki\Extension\Math\WikiTexVC;

use MediaWiki\Config\Config;
use MediaWiki\Config\HashConfig;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\Math\MathConfig;
use MediaWiki\Extension\Math\MathNativeMML;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\Math\MathNativeMML
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLmappings\BaseMethods
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLmappings\BaseMethods
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLmappings\BaseParsing
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLmappings\Util\MMLParsingUtil
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLmappings\Util\MMLutil
 */
final class ChangesTest extends MediaWikiIntegrationTestCase {

	private MathConfig $mathConfig;
	private HookContainer $hookContainer;
	private Config $mainConfig;

	/**
	 * @dataProvider provideTestCases
	 */
	public function testChanges( array $testCase ) {
		$expectedOutput = $testCase['output'];
		unset( $testCase['output'] );

		$coreValidation = $testCase['core-validation'] ?? true;
		unset( $testCase['core-validation'] );

		$rngFilePath = __DIR__ . '/mathml4-core.rng';

		MathNativeMML::renderReferenceEntry(
			$testCase,
			$this->mathConfig,
			$this->hookContainer,
			$this->mainConfig,
			$rngFilePath
		);
		// assertXmlStringEqualsXmlString ignores order of attributes
		$this->assertXmlStringEqualsXmlString( $expectedOutput, $testCase['output'], 'Output differs' );

		if ( $coreValidation !== true ) {
			$this->assertArrayHasKey( 'core-validation', $testCase, 'Core validation unexpectedly successful' );
			$this->assertArrayEquals( $coreValidation, $testCase['core-validation'], 'Core validation differs' );
		}
	}

	public static function provideTestCases() {
		$file = file_get_contents( __DIR__ . "/data/reference.json" );
		$json = json_decode( $file, true );
		$i = -1;
		foreach ( $json as $entry ) {
			$i++;
			yield "Testcase $i: " . substr( $entry['input'], 0, 20 ) => [ $entry ];
		}
	}

	private function getMathConfig() {
		return new MathConfig(
			new ServiceOptions( MathConfig::CONSTRUCTOR_OPTIONS, [
					'MathDisableTexFilter' => MathConfig::ALWAYS,
					'MathValidModes' => [ MathConfig::MODE_NATIVE_MML ],
					'MathEntitySelectorFallbackUrl' => '\\urs',
				] ),
			$this->createMock( ExtensionRegistry::class )

		);
	}

	protected function setUp(): void {
		parent::setUp();
		MediaWikiServices::allowGlobalInstanceAfterUnitTests();
		$this->mathConfig = $this->getMathConfig();
		$this->hookContainer = $this->createHookContainer();
		$this->mainConfig = new HashConfig();
		$this->mainConfig->set( 'MathEnableFormulaLinks', false );
	}
}
