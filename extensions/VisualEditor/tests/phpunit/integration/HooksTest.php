<?php

namespace MediaWiki\Extension\VisualEditor\Tests;

use MediaWiki\Extension\VisualEditor\Hooks;
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
		Hooks::onResourceLoaderGetConfigVars( $vars );

		$this->assertArrayHasKey( 'wgVisualEditorConfig', $vars );
		$veConfig = $vars['wgVisualEditorConfig'];

		foreach ( $expected as $name => $value ) {
			$this->assertArrayHasKey( $name, $veConfig );
			$this->assertSame( $value, $veConfig[$name] );
		}
	}

	public static function provideOnResourceLoaderGetConfigVars() {
		// TODO: test a lot more config!
	}

}
