<?php

namespace MediaWiki\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\CheckUser\HookHandler\RLRegisterModulesHandler;
use MediaWiki\CheckUser\Tests\Integration\SuggestedInvestigations\SuggestedInvestigationsTestTrait;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\ResourceLoader\ResourceLoader;
use MediaWikiIntegrationTestCase;

/**
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\HookHandler\RLRegisterModulesHandler
 */
class RLRegisterModulesHandlerTest extends MediaWikiIntegrationTestCase {
	use SuggestedInvestigationsTestTrait;

	/** @dataProvider provideSuggestedInvestigationModuleRegistration */
	public function testSuggestedInvestigationsModuleRegistration( bool $suggestedInvestigationsEnabled ) {
		if ( $suggestedInvestigationsEnabled ) {
			$this->enableSuggestedInvestigations();
			$this->setTemporaryHook(
				'CheckUserSuggestedInvestigationsGetSignals',
				static function ( &$signals ) {
					$signals = [ 'dev-signal-1', 'dev-signal-2' ];
				}
			);
		} else {
			$this->disableSuggestedInvestigations();
			$this->setTemporaryHook(
				'CheckUserSuggestedInvestigationsGetSignals',
				function () {
					$this->fail( 'Hook should not have been called, as suggested investigations is disabled' );
				}
			);
		}

		$handler = new RLRegisterModulesHandler(
			$this->createMock( ExtensionRegistry::class ),
			$this->getServiceContainer()->get( 'CheckUserHookRunner' ),
			$this->getServiceContainer()->getMainConfig()
		);

		// Run hook and save modules loaded to an array to check against in the assertion
		$rlModules = [];
		$rl = $this->createMock( ResourceLoader::class );
		$rl->method( 'register' )
			->willReturnCallback( static function ( array $modules ) use ( &$rlModules ) {
				$rlModules = array_merge( $rlModules, $modules );
			} );
		$handler->onResourceLoaderRegisterModules( $rl );

		$this->assertArrayHasKey( 'ext.checkUser.suggestedInvestigations', $rlModules );

		if ( $suggestedInvestigationsEnabled ) {
			$this->assertArrayContains(
				[
					'checkuser-suggestedinvestigations-risk-signals-popover-body-dev-signal-1',
					'checkuser-suggestedinvestigations-risk-signals-popover-body-dev-signal-2',
				],
				$rlModules['ext.checkUser.suggestedInvestigations']['messages']
			);
		}
	}

	public static function provideSuggestedInvestigationModuleRegistration(): array {
		return [
			'Suggested investigations enabled' => [ true ],
			'Suggested investigations not enabled' => [ false ],
		];
	}
}
