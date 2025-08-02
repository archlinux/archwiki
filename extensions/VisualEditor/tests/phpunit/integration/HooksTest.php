<?php

namespace MediaWiki\Extension\VisualEditor\Tests;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\VisualEditor\Hooks;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\VisualEditor\Hooks
 * @group Database
 */
class HooksTest extends MediaWikiIntegrationTestCase {

	/**
	 * @dataProvider provideOnResourceLoaderGetConfigVars
	 */
	public function testOnResourceLoaderGetConfigVars( array $config, array $expected ) {
		$this->overrideConfigValues( $config );

		$vars = [];
		( new Hooks( ExtensionRegistry::getInstance() ) )->onResourceLoaderGetConfigVars( $vars, '', new HashConfig() );

		$this->assertArrayHasKey( 'wgVisualEditorConfig', $vars );
		$veConfig = $vars['wgVisualEditorConfig'];

		foreach ( $expected as $name => $value ) {
			$this->assertArrayHasKey( $name, $veConfig );
			$this->assertSame( $value, $veConfig[$name] );
		}
	}

	public static function provideOnResourceLoaderGetConfigVars() {
		yield [ [], [] ];
		// TODO: test a lot more config!
	}

	/**
	 * @dataProvider provideLoadingTemplateDiscovery
	 */
	public function testLoadingTemplateDiscovery( bool $tdLoaded, bool $featureFlagEnabled, bool $hasModule ) {
		$extensionRegistry = $this->createMock( ExtensionRegistry::class );
		$extensionRegistry->method( 'isLoaded' )->willReturnCallback(
			static function ( $extension ) use ( $tdLoaded ) {
				return $extension === 'TemplateData' && $tdLoaded;
			}
		);
		$extensionRegistry->method( 'getAttribute' )
			->willReturn( [] );
		$this->overrideConfigValues( [ 'TemplateDataEnableDiscovery' => $featureFlagEnabled ] );

		$vars = [];
		( new Hooks( $extensionRegistry ) )->onResourceLoaderGetConfigVars( $vars, '', new HashConfig() );

		$this->assertArrayHasKey( 'wgVisualEditorConfig', $vars );
		if ( $hasModule ) {
			$this->assertArrayContains(
				[ 'ext.templateData.templateDiscovery' ],
				$vars['wgVisualEditorConfig']['pluginModules']
			);
		} else {
			$this->assertNotContains(
				'ext.templateData.templateDiscovery',
				$vars['wgVisualEditorConfig']['pluginModules']
			);
		}
	}

	public static function provideLoadingTemplateDiscovery(): array {
		return [
			[ false, false, false ],
			[ false, true, false ],
			[ true, false, false ],
			[ true, true, true ],
		];
	}
}
