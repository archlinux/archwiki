<?php

namespace MediaWiki\CheckUser\Tests\Unit\HookHandler;

use MediaWiki\CheckUser\Hook\HookRunner;
use MediaWiki\CheckUser\HookHandler\RLRegisterModulesHandler;
use MediaWiki\Config\HashConfig;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\ResourceLoader\ResourceLoader;
use MediaWikiUnitTestCase;

/**
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\HookHandler\RLRegisterModulesHandler
 */
class RLRegisterModulesHandlerTest extends MediaWikiUnitTestCase {

	/** @dataProvider provideTestIPInfoHooksModuleRegistration */
	public function testIPInfoHooksModuleRegistration( array $extensionRegistryReturnMap, bool $isLoaded ) {
		$mockExtensionRegistry = $this->createMock( ExtensionRegistry::class );
		$mockExtensionRegistry->method( 'isLoaded' )
			->willReturnMap( $extensionRegistryReturnMap );
		$handler = new RLRegisterModulesHandler(
			$mockExtensionRegistry,
			$this->createMock( HookRunner::class ),
			new HashConfig( [ 'CheckUserSuggestedInvestigationsEnabled' => false ] )
		);

		// Run hook and save modules loaded to an array to check against in the assertion
		$rlModules = [];
		$rl = $this->createMock( ResourceLoader::class );
		$rl->method( 'register' )
			->willReturnCallback( static function ( array $modules ) use ( &$rlModules ) {
				$rlModules = array_merge( $rlModules, $modules );
			} );
		$handler->onResourceLoaderRegisterModules( $rl );

		$this->assertEquals( array_key_exists( 'ext.checkUser.ipInfo.hooks', $rlModules ), $isLoaded );
		$this->assertArrayHasKey( 'ext.checkUser.tempAccountOnboarding', $rlModules );

		if ( $isLoaded ) {
			$this->assertContains(
				'ipinfo-preference-use-agreement',
				$rlModules['ext.checkUser.tempAccountOnboarding']['messages'],
				'tempAccountOnboarding module has ipinfo agreement message',
			);
		}
	}

	public static function provideTestIPInfoHooksModuleRegistration() {
		return [
			'IPInfo not loaded, module not loaded' => [
				'extensionRegistryReturnMap' => [ [ 'IPInfo', '*', false ] ],
				'isLoaded' => false,
			],
			'IPInfo loaded, module loaded' => [
				'extensionRegistryReturnMap' => [ [ 'IPInfo', '*', true ] ],
				'isLoaded' => true,
			],
		];
	}
}
