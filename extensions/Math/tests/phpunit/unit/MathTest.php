<?php
namespace MediaWiki\Extension\Math\Tests;

use ExtensionRegistry;
use HashConfig;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\Math\Math;
use MediaWiki\Extension\Math\MathConfig;
use MediaWiki\MediaWikiServices;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Math\Math
 */
class MathTest extends MediaWikiUnitTestCase {
	private const DUMMY_URL = 'https://example.com/api.php';

	public function testGetMathConfigService() {
		$config = new HashConfig( [
			'MathDisableTexFilter' => MathConfig::NEW,
			'MathValidModes' => [ MathConfig::MODE_SOURCE ],
			'MathEntitySelectorFallbackUrl' => self::DUMMY_URL,
		] );
		$services = new MediaWikiServices( $config );
		$services->defineService( 'Math.Config',
			static function ( MediaWikiServices $services ){
			return new MathConfig(
				new ServiceOptions(
					MathConfig::CONSTRUCTOR_OPTIONS,
					$services->get( 'BootstrapConfig' ) ),
				ExtensionRegistry::getInstance()
				);
			}
		);
		$mathConfig = Math::getMathConfig( $services );
		$this->assertStringContainsString( 'new', $mathConfig->texCheckDisabled() );
	}
}
