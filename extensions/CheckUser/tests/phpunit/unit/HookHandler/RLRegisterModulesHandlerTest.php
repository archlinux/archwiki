<?php

namespace MediaWiki\CheckUser\Tests\Unit\HookHandler;

use MediaWiki\CheckUser\HookHandler\RLRegisterModulesHandler;
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
		$handler = new RLRegisterModulesHandler( $mockExtensionRegistry );

		// Run hook and save modules loaded to an array to check against in the assertion
		$rlModules = [];
		$rl = $this->createMock( ResourceLoader::class );
		$rl->method( 'register' )
			->willReturnCallback( static function ( array $modules ) use ( &$rlModules ) {
				$rlModules = array_merge( $rlModules, $modules );
			} );
		$handler->onResourceLoaderRegisterModules( $rl );

		$this->assertEquals( array_key_exists( 'ext.checkUser.ipInfo.hooks', $rlModules ), $isLoaded );
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
