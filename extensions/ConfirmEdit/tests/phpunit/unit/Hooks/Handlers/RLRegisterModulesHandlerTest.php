<?php

namespace MediaWiki\Extension\ConfirmEdit\Tests\Unit\Hooks\Handlers;

use MediaWiki\Extension\ConfirmEdit\Hooks\Handlers\RLRegisterModulesHandler;
use MediaWiki\Extension\ConfirmEdit\Services\LoadedCaptchasProvider;
use MediaWiki\ResourceLoader\ResourceLoader;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\ConfirmEdit\Hooks\Handlers\RLRegisterModulesHandler
 */
class RLRegisterModulesHandlerTest extends MediaWikiUnitTestCase {
	/** @dataProvider provideCaptchaModuleRegistration */
	public function testCaptchaModuleRegistration( array $captchasEnabled, array $expectedModuleNames ) {
		$mockLoadedCaptchasProvider = $this->createMock( LoadedCaptchasProvider::class );
		$mockLoadedCaptchasProvider->method( 'getLoadedCaptchas' )
			->willReturn( $captchasEnabled );

		$handler = new RLRegisterModulesHandler( $mockLoadedCaptchasProvider );

		// Run the hook with a mock ResourceLoader instance that stores the list of registered modules for
		// later assertion
		$rlModules = [];
		$rl = $this->createMock( ResourceLoader::class );
		$rl->method( 'register' )
			->willReturnCallback( static function ( array $modules ) use ( &$rlModules ) {
				$rlModules = array_merge( $rlModules, $modules );
			} );
		$handler->onResourceLoaderRegisterModules( $rl );

		$this->assertArrayEquals( $expectedModuleNames, array_keys( $rlModules ) );
	}

	public static function provideCaptchaModuleRegistration(): array {
		return [
			'hCaptcha is not enabled' => [ [ 'SimpleCaptcha' ], [] ],
			'hCaptcha is enabled' => [
				[ 'SimpleCaptcha', 'HCaptcha' ],
				[ 'ext.confirmEdit.hCaptcha', 'ext.confirmEdit.hCaptcha.styles' ],
			],
		];
	}
}
