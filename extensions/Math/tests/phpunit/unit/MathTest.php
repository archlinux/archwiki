<?php
namespace MediaWiki\Extension\Math\Tests;

use MediaWiki\Config\HashConfig;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\Math\Math;
use MediaWiki\Extension\Math\MathConfig;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\VisitorFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;
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
			function ( MediaWikiServices $services ) use ( $config ) {
				return new MathConfig(
					new ServiceOptions(
						MathConfig::CONSTRUCTOR_OPTIONS,
						$config
					),
					$this->createMock( ExtensionRegistry::class )
				);
			}
		);
		$mathConfig = Math::getMathConfig( $services );
		$this->assertStringContainsString( 'new', $mathConfig->texCheckDisabled() );
	}

	public function testGetVisitorFactory() {
		$config = new HashConfig( [] );
		$services = new MediaWikiServices( $config );
		$mockVisitorFactory = $this->createMock( VisitorFactory::class );
		$services->defineService( 'Math.MathMLTreeVisitor',
			static function ( MediaWikiServices $services ) use ( $mockVisitorFactory ) {
				return $mockVisitorFactory;
			}
		);
		$this->setService( 'Math.MathMLTreeVisitor', $mockVisitorFactory );
		$result = Math::getVisitorFactory( $services );
		$this->assertSame( $mockVisitorFactory, $result );
		$treeVisitor = $this->getService( 'Math.MathMLTreeVisitor' );
		$this->assertSame( $mockVisitorFactory, $treeVisitor );
	}
}
