<?php
namespace MediaWiki\Extension\Math\WikiTexVC;

use MediaWiki\Config\Config;
use MediaWiki\Config\HashConfig;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\Math\MathConfig;
use MediaWiki\Extension\Math\MathNativeMML;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Math\MathNativeMML
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLmappings\BaseMethods
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLmappings\BaseMethods
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLmappings\BaseParsing
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLmappings\Util\MMLParsingUtil
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLmappings\Util\MMLutil
 */
final class ChangesTest extends MediaWikiUnitTestCase {
	private MathConfig $mathConfig;
	private HookContainer $hookContainer;
	private Config $mainConfig;

	/**
	 * @dataProvider provideTestCases
	 */
	public function testChanges( $tc ) {
		$old = $tc['output'];
		unset( $tc['output'] );
		MathNativeMML::renderReferenceEntry( $tc, $this->mathConfig, $this->hookContainer, $this->mainConfig );
		$this->assertEquals( $old, $tc['output'] );
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
		$this->mathConfig = $this->getMathConfig();
		$this->hookContainer = $this->createHookContainer();
		$this->mainConfig = new HashConfig();
		$this->mainConfig->set( 'MathEnableFormulaLinks', false );
	}
}
