<?php

namespace MediaWiki\Extension\CheckUser\HookHandler;

use GlobalPreferences\GlobalPreferencesFactory;
use MediaWiki\Config\Config;
use MediaWiki\Extension\CheckUser\Services\CheckUserIPRevealManager;
use MediaWiki\Extension\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\IPInfo\HookHandler\AbstractPreferencesHandler;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\Preferences\PreferencesFactory;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Skin\Skin;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityUtils;

class PageDisplay implements BeforePageDisplayHook {
	public function __construct(
		private readonly Config $config,
		private readonly CheckUserPermissionManager $checkUserPermissionManager,
		private readonly CheckUserIPRevealManager $checkUserIPRevealManager,
		private readonly TempUserConfig $tempUserConfig,
		private readonly UserOptionsLookup $userOptionsLookup,
		private readonly ExtensionRegistry $extensionRegistry,
		private readonly UserIdentityUtils $userIdentityUtils,
		private readonly PreferencesFactory $preferencesFactory,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$this->loadIPInfoGlobalContributionsLink( $out, $skin );
		$this->addUserInfoCardConfigVars( $out );
		$this->addTemporaryAccountsOnboardingDialog( $out );
		$this->addIPRevealButtons( $out );
	}

	/**
	 * Add IP reveal buttons to certain pages if the user has the necessary permissions.
	 *
	 * @param OutputPage $out
	 * @return void
	 */
	private function addIPRevealButtons( OutputPage $out ) {
		if ( !$this->checkUserIPRevealManager->shouldAddIPRevealButtons( $out ) ) {
			return;
		}

		$permStatus = $this->checkUserPermissionManager->canAccessTemporaryAccountIPAddresses(
			$out->getAuthority()
		);

		// Config needed for a js features on Special:Block
		$title = $out->getTitle();
		if ( $title->isSpecial( 'Block' ) ) {
			$out->addJsConfigVars( [
				'wgCUDMaxAge' => $this->config->get( 'CUDMaxAge' ),
				'wgTemporaryAccountIPRevealAllowed' => $permStatus->isGood(),
			] );
		}

		$out->addModules( 'ext.checkUser.tempAccounts' );
		$out->addModuleStyles( 'ext.checkUser.styles' );

		$out->addJSConfigVars( [
			'wgCheckUserAbuseFilterExtensionLoaded' =>
				$this->extensionRegistry->isLoaded( 'Abuse Filter' ),
			'wgCheckUserIsPerformerBlocked' => $permStatus->getBlock() !== null,
			'wgCheckUserTemporaryAccountMaxAge' => $this->config->get( 'CheckUserTemporaryAccountMaxAge' ),
			// Unconditionally set to true as CheckUserIPRevealManager already checked
			// the user's access to this tool. This config is needed for client-side code
			// to decide whether to add the buttons (T399994)
			'wgCheckUserTemporaryAccountIPRevealAllowed' => true,
			'wgCheckUserSpecialPagesWithoutIPRevealButtons' =>
				$this->config->get( 'CheckUserSpecialPagesWithoutIPRevealButtons' ),
			'wgCheckUserContribsPageLocalName' => SpecialPage::getTitleValueFor( 'Contributions' )->getText(),
		] );
	}

	/**
	 * Export JS config variables for UserInfoCard to determine permissions.
	 *
	 * @param OutputPage $out
	 * @return void
	 */
	private function addUserInfoCardConfigVars( OutputPage $out ) {
		$performer = $out->getUser();
		if ( !$performer->isNamed() ) {
			return;
		}

		$hasEnabledInfoCard = $this->userOptionsLookup->getBoolOption(
			$performer,
			Preferences::ENABLE_USER_INFO_CARD
		);

		if ( $hasEnabledInfoCard ) {
			$authority = $out->getAuthority();

			$out->addJsConfigVars( [
				'wgCheckUserCanViewCheckUserLog' =>
					$authority->isAllowed( 'checkuser-log' ),
				'wgCheckUserCanBlock' =>
					$authority->isAllowed( 'block' ),
				'wgCheckUserCanPerformCheckUser' =>
					$authority->isAllowed( 'checkuser' ),
				'wgCheckUserCanAccessTemporaryAccountLog' =>
					$authority->isAllowed( 'checkuser-temporary-account-log' ),
				'wgCheckUserCanViewSuggestedInvestigations' =>
					$authority->isAllowed( 'checkuser-suggested-investigations' ) &&
					$this->config->get( 'CheckUserSuggestedInvestigationsEnabled' ),
			] );
		}
	}

	/**
	 * Returns whether the given preference is enabled for the given user. If the GlobalPreferences extension
	 * is installed, then the global value of the preference is returned ignoring any local override.
	 *
	 * @param UserIdentity $user The user to get the preference value for
	 * @param string $preference The preference to check
	 * @return bool Whether the preference is enabled
	 */
	private function getGlobalPreferenceValue( UserIdentity $user, string $preference ): bool {
		if (
			$this->extensionRegistry->isLoaded( 'GlobalPreferences' ) &&
			$this->preferencesFactory instanceof GlobalPreferencesFactory
		) {
			// If GlobalPreferences is installed, then we want to use the value from
			// there over the local preference. This is because we will set the value globally
			// in the dialog if that is possible, so want to have it unchecked if locally
			// enabled but globally disabled.
			$globalPreferences = $this->preferencesFactory->getGlobalPreferencesValues( $user );
			if ( $globalPreferences !== false ) {
				return isset( $globalPreferences[$preference] ) &&
					$globalPreferences[$preference];
			}
		}
		return $this->userOptionsLookup->getBoolOption( $user, $preference );
	}

	/**
	 * Show the temporary accounts onboarding dialog if the user has never seen the dialog before,
	 * has permissions to reveal IPs (ignoring the preference check) and the user is
	 * viewing any of the history page, Special:Watchlist, or Special:RecentChanges.
	 * We show the dialog even if the acting user is blocked
	 *
	 * @return void
	 */
	private function addTemporaryAccountsOnboardingDialog( OutputPage $out ) {
		if ( !$this->tempUserConfig->isKnown() ) {
			return;
		}

		$action = $out->getRequest()->getVal( 'action' );
		$title = $out->getTitle();
		if (
			$action !== 'history' &&
			!$title->isSpecial( 'Watchlist' ) &&
			!$title->isSpecial( 'Recentchanges' )
		) {
			return;
		}

		if ( !$out->getAuthority()->isAllowedAny(
			'checkuser-temporary-account-no-preference',
			'checkuser-temporary-account'
		) ) {
			return;
		}

		$userHasSeenDialog = $this->userOptionsLookup->getBoolOption(
			$out->getUser(),
			Preferences::TEMPORARY_ACCOUNTS_ONBOARDING_DIALOG_SEEN
		);
		if ( !$userHasSeenDialog ) {
			$out->addHtml( '<div id="ext-checkuser-tempaccountsonboarding-app"></div>' );
			$out->addModules( 'ext.checkUser.tempAccountOnboarding' );
			$out->addModuleStyles( 'ext.checkUser.styles' );
			$out->addModuleStyles( 'ext.checkUser.images' );

			// Allow the dialog to hide the IPInfo step and/or IPInfo preference depending on the
			// rights of the user and if IPInfo is installed.
			$ipInfoLoaded = $this->extensionRegistry->isLoaded( 'IPInfo' );
			$out->addJsConfigVars( [
				'wgCheckUserIPInfoExtensionLoaded' => $ipInfoLoaded,
				'wgCheckUserUserHasIPInfoRight' => $ipInfoLoaded &&
					$out->getAuthority()->isAllowed( 'ipinfo' ),
				'wgCheckUserIPInfoPreferenceChecked' => $ipInfoLoaded &&
					$this->getGlobalPreferenceValue(
						$out->getUser(),
						AbstractPreferencesHandler::IPINFO_USE_AGREEMENT
					),
				'wgCheckUserIPRevealPreferenceGloballyChecked' => $this->getGlobalPreferenceValue(
					$out->getUser(),
					Preferences::ENABLE_IP_REVEAL
				),
				'wgCheckUserIPRevealPreferenceLocallyChecked' =>
					$this->userOptionsLookup->getBoolOption( $out->getUser(), Preferences::ENABLE_IP_REVEAL ),
				'wgCheckUserGlobalPreferencesExtensionLoaded' =>
					$this->extensionRegistry->isLoaded( 'GlobalPreferences' ),
				'wgCheckUserTemporaryAccountAutoRevealPossible' =>
					// A more permissive check than CheckUserPermissionManager::canAutoRevealIPAddresses
					// because user won't have necessarily enabled IP reveal for temporary accounts which is
					// a prereq for auto-reveal
					$this->extensionRegistry->isLoaded( 'GlobalPreferences' ) &&
					$out->getAuthority()->isAllowed( 'checkuser-temporary-account-auto-reveal' ),
				'wgCheckUserAutoRevealMaximumExpiry' =>
					$this->config->get( 'CheckUserAutoRevealMaximumExpiry' ),
			] );
		}
	}

	/**
	 * If IPInfo is enabled and the user has access to Special:GlobalContributions,
	 * enable the link to Special:GC on IPInfo's infobox widget on pages where it's loaded
	 *
	 * @param OutputPage $out
	 * @param Skin $skin
	 * @return void
	 */
	private function loadIPInfoGlobalContributionsLink( $out, $skin ): void {
		if ( !$this->extensionRegistry->isLoaded( 'IPInfo' ) ) {
			return;
		}

		// If no relevant user exists or is a registered account, return early since IPInfo only
		// supports IPs and temporary accounts
		$relevantUser = $skin->getRelevantUser();
		if ( !$relevantUser || $this->userIdentityUtils->isNamed( $relevantUser ) ) {
			return;
		}

		// If page isn't one of IPInfo's supported pages, return early
		$title = $out->getTitle();
		if (
			!$title->isSpecial( 'Contributions' ) &&
			!$title->isSpecial( 'DeletedContributions' ) &&
			!$title->isSpecial( 'IPContributions' )
		) {
			return;
		}

		// If user cannot access Special:GlobalContributions, return early
		if (
			!$this->checkUserPermissionManager->canAccessUserGlobalContributions(
				$out->getAuthority(),
				$relevantUser->getName()
			)->isGood()
		) {
			return;
		}

		// Relevant user is an IP or temporary account, page is one where IPInfo's infobox is
		// expected to load, and accessing user has permission to view Special:GlobalContributions.
		// Add module to load Special:GC link to IPInfo infobox.
		$out->addModules( 'ext.checkUser.ipInfo.hooks' );
	}
}
