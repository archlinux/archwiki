<?php

namespace MediaWiki\CheckUser\Tests\Integration\HookHandler;

use ArrayUtils;
use MediaWiki\CheckUser\HookHandler\PageDisplay;
use MediaWiki\Config\HashConfig;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\IPInfo\HookHandler\AbstractPreferencesHandler;
use MediaWiki\MainConfigNames;
use MediaWiki\Skin\Skin;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\User\UserOptionsManager;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\CheckUser\HookHandler\PageDisplay
 * @group Database
 */
class PageDisplayDatabaseTest extends MediaWikiIntegrationTestCase {

	use MockAuthorityTrait;
	use TempUserTestTrait;

	protected function setUp(): void {
		parent::setUp();

		// We don't want to test specifically the CentralAuth implementation of the CentralIdLookup. As such, force it
		// to be the local provider.
		$this->overrideConfigValue( MainConfigNames::CentralIdLookupProvider, 'local' );
	}

	/** @dataProvider provideOnBeforePageDisplayForOnboardingWhenIPInfoPreferenceIsGlobal */
	public function testOnBeforePageDisplayForOnboardingWhenIPInfoPreferenceIsGlobal(
		bool $globalPreferenceValue, bool $localPreferenceValue
	) {
		$this->markTestSkippedIfExtensionNotLoaded( 'GlobalPreferences' );
		$this->markTestSkippedIfExtensionNotLoaded( 'IPInfo' );

		// Set up pre-requisites for seeing the onboarding dialog
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setTitle( SpecialPage::getTitleFor( 'Watchlist' ) );
		$this->enableAutoCreateTempUser();

		$user = $this->getTestUser()->getUser();
		$authority = $this->mockUserAuthorityWithPermissions(
			$user, [ 'checkuser-temporary-account-no-preference', 'ipinfo' ]
		);

		$context->setAuthority( $authority );
		$output = $context->getOutput();
		$output->setContext( $context );

		// Set the global value and local override
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$userOptionsManager->setOption(
			$user, AbstractPreferencesHandler::IPINFO_USE_AGREEMENT,
			$globalPreferenceValue, UserOptionsManager::GLOBAL_CREATE
		);
		$userOptionsManager->saveOptions( $user );
		$userOptionsManager->setOption(
			$user, AbstractPreferencesHandler::IPINFO_USE_AGREEMENT,
			$localPreferenceValue, UserOptionsManager::GLOBAL_OVERRIDE
		);
		$userOptionsManager->saveOptions( $user );

		$pageDisplayHookHandler = new PageDisplay(
			new HashConfig( [
				'CheckUserTemporaryAccountMaxAge' => 1234,
				'CheckUserSpecialPagesWithoutIPRevealButtons' => [],
				'CUDMaxAge' => 12345,
				'CheckUserAutoRevealMaximumExpiry' => 1,
			] ),
			$this->getServiceContainer()->get( 'CheckUserPermissionManager' ),
			$this->getServiceContainer()->get( 'CheckUserIPRevealManager' ),
			$this->getServiceContainer()->getTempUserConfig(),
			$this->getServiceContainer()->getUserOptionsLookup(),
			$this->getServiceContainer()->getExtensionRegistry(),
			$this->getServiceContainer()->getUserIdentityUtils(),
			$this->getServiceContainer()->getPreferencesFactory()
		);

		$pageDisplayHookHandler->onBeforePageDisplay(
			$output, $this->createMock( Skin::class )
		);

		$expectedConfigVars = [
			'wgCheckUserIPInfoExtensionLoaded' => true,
			'wgCheckUserUserHasIPInfoRight' => true,
			'wgCheckUserIPInfoPreferenceChecked' => $globalPreferenceValue,
		];

		$this->assertContains( 'ext.checkUser.tempAccountOnboarding', $output->getModules() );
		$this->assertArrayContains( [ 'ext.checkUser.images', 'ext.checkUser.styles' ], $output->getModuleStyles() );
		$this->assertArrayContains( $expectedConfigVars, $output->getJsConfigVars() );
	}

	public static function provideOnBeforePageDisplayForOnboardingWhenIPInfoPreferenceIsGlobal() {
		$testCases = ArrayUtils::cartesianProduct(
			// global preference value
			[ false, true ],
			// local preference value
			[ false, true ]
		);

		foreach ( $testCases as $params ) {
			[ $globalPreferenceValue, $localPreferenceValue ] = $params;

			$description = sprintf(
				'IPInfo use agreement preference %s globally and %s locally',
				$globalPreferenceValue ? 'enabled' : 'disabled',
				$localPreferenceValue ? 'enabled' : 'disabled'
			);

			yield $description => $params;
		}
	}
}
