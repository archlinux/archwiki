<?php

namespace MediaWiki\CheckUser\Tests\Integration\HookHandler;

use ArrayUtils;
use MediaWiki\Block\Block;
use MediaWiki\CheckUser\CheckUserPermissionStatus;
use MediaWiki\CheckUser\HookHandler\PageDisplay;
use MediaWiki\CheckUser\HookHandler\Preferences;
use MediaWiki\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\Config\HashConfig;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Skin\Skin;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\Title\Title;
use MediaWiki\User\Options\StaticUserOptionsLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\CheckUser\HookHandler\PageDisplay
 */
class PageDisplayTest extends MediaWikiIntegrationTestCase {

	use MockAuthorityTrait;
	use TempUserTestTrait;

	/**
	 * @dataProvider provideOnBeforePageDisplayCases
	 *
	 * @param string|null $specialPageName The name of the special page being viewed,
	 * or `null` if not a special page
	 * @param string|null $actionName The action being performed, or `null` if no action should be set
	 * @param bool $tempAccountsKnown Whether temporary accounts are known
	 * @param bool $hasSeenOnboardingDialog Whether the user has seen the onboarding dialog
	 * @param bool $hasEnabledIpReveal Whether the user has enabled the IP reveal preference
	 * @param bool $hasIpRevealPermission Whether the user has the permission to reveal IPs
	 * @param bool $hasIpInfoPermission Whether the user has the permission to access IP information
	 * @param bool $isBlockedSitewide Whether the user is sitewide blocked
	 * @param bool $isOnboardingDialogEnabled Whether the onboarding dialog is enabled in local configuration
	 * @param bool $isIpInfoAvailable Whether the IPInfo extension is loaded
	 */
	public function testOnBeforePageDisplay(
		?string $specialPageName,
		?string $actionName,
		bool $tempAccountsKnown,
		bool $hasSeenOnboardingDialog,
		bool $hasEnabledIpReveal,
		bool $hasIpRevealPermission,
		bool $hasIpInfoPermission,
		bool $isBlockedSitewide,
		bool $isOnboardingDialogEnabled,
		bool $isIpInfoAvailable
	): void {
		if ( $tempAccountsKnown ) {
			$this->enableAutoCreateTempUser();
		} else {
			$this->disableAutoCreateTempUser();
		}

		$context = new DerivativeContext( RequestContext::getMain() );

		// Set up either a special page or a main namespace article as the page being viewed.
		if ( $specialPageName !== null ) {
			$context->setTitle( SpecialPage::getTitleFor( $specialPageName ) );
		} else {
			$context->setTitle( Title::newFromText( 'Test' ) );
			$context->getRequest()->setVal( 'action', $actionName );
		}

		$permissions = [];

		if ( $hasIpRevealPermission ) {
			$permissions[] = 'checkuser-temporary-account';
		}

		if ( $hasIpInfoPermission ) {
			$permissions[] = 'ipinfo';
		}

		if ( $isBlockedSitewide ) {
			$block = $this->createMock( Block::class );
			$block->method( 'isSitewide' )
				->willReturn( true );

			$testAuthority = $this->mockUserAuthorityWithBlock(
				new UserIdentityValue( 123, 'Test' ),
				$block,
				$permissions
			);
		} else {
			$testAuthority = $this->mockRegisteredAuthorityWithPermissions( $permissions );
		}

		$this->setService( 'UserOptionsLookup', new StaticUserOptionsLookup(
			[], [
				Preferences::TEMPORARY_ACCOUNTS_ONBOARDING_DIALOG_SEEN => (int)$hasSeenOnboardingDialog,
				Preferences::ENABLE_IP_REVEAL => (int)$hasEnabledIpReveal,
			]
		) );

		$context->setAuthority( $testAuthority );
		$output = $context->getOutput();
		$output->setContext( $context );

		$extensionRegistry = $this->createMock( ExtensionRegistry::class );
		$extensionRegistry->method( 'isLoaded' )
			->with( 'IPInfo' )
			->willReturn( $isIpInfoAvailable );

		$pageDisplayHookHandler = new PageDisplay(
			new HashConfig( [
				'CheckUserTemporaryAccountMaxAge' => 1234,
				'CheckUserSpecialPagesWithoutIPRevealButtons' => [ 'BlockList' ],
				'CUDMaxAge' => 12345,
				'CheckUserEnableTempAccountsOnboardingDialog' => $isOnboardingDialogEnabled,
			] ),
			$this->getServiceContainer()->get( 'CheckUserPermissionManager' ),
			$this->getServiceContainer()->getTempUserConfig(),
			$this->getServiceContainer()->getUserOptionsLookup(),
			$extensionRegistry,
			$this->getServiceContainer()->getUserIdentityUtils()
		);

		$pageDisplayHookHandler->onBeforePageDisplay(
			$output, $this->createMock( Skin::class )
		);

		$expectedModules = [];
		$expectedModuleStyles = [];
		$expectedConfigVars = [];

		// Temporary account-related configuration and modules should only be added to the output
		// only on special pages and selected action pages, and only if temporary accounts
		// are known on this wiki and the acting user has appropriate permissions.
		if (
			$tempAccountsKnown &&
			( $specialPageName || $actionName ) &&
			$hasIpRevealPermission
		) {
			if ( $hasEnabledIpReveal ) {
				$expectedModules[] = 'ext.checkUser.tempAccounts';
				$expectedModuleStyles[] = 'ext.checkUser.styles';
				$expectedConfigVars += [
					'wgCheckUserTemporaryAccountMaxAge' => 1234,
					'wgCheckUserSpecialPagesWithoutIPRevealButtons' => [ 'BlockList' ],
					'wgCheckUserIsPerformerBlocked' => $isBlockedSitewide,
				];

				if ( $specialPageName === 'Block' ) {
					$expectedConfigVars['wgCUDMaxAge'] = 12345;
				}
			}

			if (
				$isOnboardingDialogEnabled &&
				!$hasSeenOnboardingDialog &&
				( $actionName === 'history' || $specialPageName === 'Watchlist' )
			) {
				$expectedConfigVars += [
					'wgCheckUserIPInfoExtensionLoaded' => $isIpInfoAvailable,
					'wgCheckUserUserHasIPInfoRight' => $isIpInfoAvailable && $hasIpInfoPermission
				];
				$expectedModules[] = 'ext.checkUser.tempAccountOnboarding';
				$expectedModuleStyles[] = 'ext.checkUser.images';
				$expectedModuleStyles[] = 'ext.checkUser.styles';
			}
		}

		$this->assertArrayEquals( $expectedModules, $output->getModules() );
		$this->assertArrayEquals(
			array_unique( $expectedModuleStyles ),
			$output->getModuleStyles()
		);
		$this->assertArrayEquals(
			$expectedConfigVars,
			$output->getJsConfigVars(),
			false,
			true
		);
	}

	public static function provideOnBeforePageDisplayCases(): iterable {
		$testCases = ArrayUtils::cartesianProduct(
			// special pages
			[ 'Watchlist', 'Block', null ],
			// actions
			[ 'info', 'history', null ],
			// whether temporary accounts are known
			[ true, false ],
			// whether the user has seen the onboarding dialog
			[ true, false ],
			// whether the user has enabled the IP reveal preference
			[ true, false ],
			// whether the user has the permission to reveal IPs
			[ true, false ],
			// whether the user has the permission to access IP information
			[ true, false ],
			// whether the user is sitewide blocked
			[ true, false ],
			// whether the onboarding dialog is enabled in local configuration
			[ true, false ],
			// whether the IPInfo extension is loaded
			[ true, false ]
		);

		foreach ( $testCases as $params ) {
			[
				$specialPageName,
				$actionName,
				$tempAccountsKnown,
				$hasSeenOnboardingDialog,
				$hasEnabledIpReveal,
				$hasIpRevealPermission,
				$hasIpInfoPermission,
				$isBlockedSitewide,
				$isOnboardingDialogEnabled,
				$isIpInfoAvailable,
			] = $params;

			// Special pages can't have actions.
			if ( $specialPageName !== null && $actionName !== null ) {
				continue;
			}

			// The presence of IPInfo and related permissions only influence config variables
			// related to the onboarding dialog, so don't generate permutations involving them
			// if we do not expect to show the dialog.
			if ( $hasSeenOnboardingDialog || !$isOnboardingDialogEnabled ) {
				if ( $isIpInfoAvailable || $hasIpInfoPermission ) {
					continue;
				}
			}

			// Exclude permutations where features would be doubly disabled.
			if ( !$isOnboardingDialogEnabled && $hasSeenOnboardingDialog ) {
				continue;
			}

			if ( $isBlockedSitewide && !$hasIpRevealPermission ) {
				continue;
			}

			if (
				( !$tempAccountsKnown || ( $actionName === null && $specialPageName === null ) ) &&
				(
					$isBlockedSitewide ||
					!$hasIpRevealPermission ||
					$hasSeenOnboardingDialog ||
					!$isOnboardingDialogEnabled
				)
			) {
				continue;
			}

			$description = sprintf(
				'%s%s temporary accounts %s, onboarding dialog %s, IP reveal %s, ' .
				'%s IP reveal permission, %s IP info permission, %s, onboarding dialog %s, ' .
				'IPInfo extension %s',
				$specialPageName ? "Special:$specialPageName, " : '',
				$actionName ? "action=$actionName," : '',
				$tempAccountsKnown ? 'known' : 'not known',
				$hasSeenOnboardingDialog ? 'seen' : 'not seen',
				$hasEnabledIpReveal ? 'enabled' : 'disabled',
				$hasIpRevealPermission ? 'with' : 'no',
				$hasIpInfoPermission ? 'with' : 'no',
				$isBlockedSitewide ? 'blocked sitewide' : 'not blocked',
				$isOnboardingDialogEnabled ? 'enabled' : 'disabled',
				$isIpInfoAvailable ? 'loaded' : 'not loaded'
			);

			yield $description => $params;
		}
	}

	/** @dataProvider provideOnBeforePageDisplayForIPInfoHookCases */
	public function testOnBeforePageDisplayForIPInfoHook(
		string $pageTitle,
		UserIdentityValue $target,
		bool $canViewSpecialGC,
		bool $ipInfoLoaded,
		bool $shouldLoadModule
	) {
		// Set up a IContextSource where the title is $pageTitle
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setTitle( SpecialPage::getTitleFor( $pageTitle ) );
		$testAuthority = $this->mockRegisteredUltimateAuthority();
		$context->setAuthority( $testAuthority );
		$output = $context->getOutput();
		$output->setContext( $context );

		$skin = $this->createMock( Skin::class );
		$skin->method( 'getRelevantUser' )
			->willReturn( $target );

		$cuPermissionManagerGCAccessCheck = $this->createMock( CheckUserPermissionStatus::class );
		$cuPermissionManagerGCAccessCheck->method( 'isGood' )
			->willReturn( $canViewSpecialGC );
		$cuPermissionManager = $this->createMock( CheckUserPermissionManager::class );
		$cuPermissionManager->method( 'canAccessUserGlobalContributions' )
			->willReturn( $cuPermissionManagerGCAccessCheck );

		$mockExtensionRegistry = $this->createMock( ExtensionRegistry::class );
		$mockExtensionRegistry->method( 'isLoaded' )
			->with( 'IPInfo' )
			->willReturn( $ipInfoLoaded );

		$pageDisplayHookHandler = new PageDisplay(
			new HashConfig( [
				'CheckUserEnableTempAccountsOnboardingDialog' => false,
			] ),
			$cuPermissionManager,
			$this->getServiceContainer()->getTempUserConfig(),
			$this->getServiceContainer()->getUserOptionsLookup(),
			$mockExtensionRegistry,
			$this->getServiceContainer()->getUserIdentityUtils()
		);

		$pageDisplayHookHandler->onBeforePageDisplay(
			$output, $skin
		);

		// Assert that the module is loaded as necessary
		if ( $shouldLoadModule ) {
			$this->assertContains( 'ext.checkUser.ipInfo.hooks', $output->getModules() );
		} else {
			$this->assertNotContains( 'ext.checkUser.ipInfo.hooks', $output->getModules() );
		}
	}

	/**
	 * Parameters:
	 * - Name of special page (string)
	 * - Relevant user (UserIdentityValue)
	 * - Whether the accessor can view Special:GC (bool)
	 * - Whether the Special:GC link module is loaded or not (bool)
	 */
	public static function provideOnBeforePageDisplayForIPInfoHookCases() {
		return [
			'module should load on Special:Contributions with user' => [
				'pageTitle' => 'Contributions',
				'target' => UserIdentityValue::newAnonymous( '1.2.3.4' ),
				'canViewSpecialGC' => true,
				'ipInfoLoaded' => true,
				'shouldLoadModule' => true,
			],
			'module shouldn\'t load on Special:Contributions with user' => [
				'pageTitle' => 'Contributions',
				'target' => UserIdentityValue::newRegistered( 1, 'Registered User' ),
				'canViewSpecialGC' => true,
				'ipInfoLoaded' => true,
				'shouldLoadModule' => false,
			],
			'module shouldn\'t load on Special:RecentChanges' => [
				'pageTitle' => 'Recentchanges',
				'target' => UserIdentityValue::newAnonymous( '1.2.3.4' ),
				'canViewSpecialGC' => true,
				'ipInfoLoaded' => true,
				'shouldLoadModule' => false,
			],
			'module shouldn\'t load if user has no view permissions for Special:GlobalContributions' => [
				'pageTitle' => 'Contributions',
				'target' => UserIdentityValue::newAnonymous( '1.2.3.4' ),
				'canViewSpecialGC' => false,
				'ipInfoLoaded' => true,
				'shouldLoadModule' => false,
			],
			'module shouldn\'t load if IPInfo isn\t loaded' => [
				'pageTitle' => 'Contributions',
				'target' => UserIdentityValue::newAnonymous( '1.2.3.4' ),
				'canViewSpecialGC' => true,
				'ipInfoLoaded' => false,
				'shouldLoadModule' => false,
			],
		];
	}
}
