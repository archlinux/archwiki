<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\Config\Config;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Skin\Skin;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\UserIdentityUtils;

class PageDisplay implements BeforePageDisplayHook {
	private Config $config;
	private CheckUserPermissionManager $checkUserPermissionManager;
	private UserOptionsLookup $userOptionsLookup;
	private TempUserConfig $tempUserConfig;
	private ExtensionRegistry $extensionRegistry;
	private UserIdentityUtils $userIdentityUtils;

	public function __construct(
		Config $config,
		CheckUserPermissionManager $checkUserPermissionManager,
		TempUserConfig $tempUserConfig,
		UserOptionsLookup $userOptionsLookup,
		ExtensionRegistry $extensionRegistry,
		UserIdentityUtils $userIdentityUtils
	) {
		$this->config = $config;
		$this->checkUserPermissionManager = $checkUserPermissionManager;
		$this->tempUserConfig = $tempUserConfig;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->extensionRegistry = $extensionRegistry;
		$this->userIdentityUtils = $userIdentityUtils;
	}

	/**
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$this->loadIPInfoGlobalContributionsLink( $out, $skin );

		// There is no need for the JS modules for temporary account IP reveal
		// if the wiki does not have temporary accounts enabled or known.
		if ( !$this->tempUserConfig->isKnown() ) {
			return;
		}

		// Exclude loading the JS module on pages which do not use it.
		$action = $out->getRequest()->getVal( 'action' );
		if (
			$action !== 'history' &&
			$action !== 'info' &&
			$out->getRequest()->getRawVal( 'diff' ) === null &&
			$out->getRequest()->getRawVal( 'oldid' ) === null &&
			!( $out->getTitle() && $out->getTitle()->isSpecialPage() )
		) {
			return;
		}

		$this->addTemporaryAccountsOnboardingDialog( $out );

		// Add IP reveal modules if the user has permission to use it.
		// Note we also add the module if the user is blocked
		// so that we can render the UI in a disabled state (T345639).
		$permStatus = $this->checkUserPermissionManager->canAccessTemporaryAccountIPAddresses(
			$out->getAuthority()
		);

		if ( !$permStatus->isGood() && !$permStatus->getBlock() ) {
			return;
		}

		// All checks passed, so add the JS code needed for temporary account IP reveal.

		// Config needed for a js-added message on Special:Block
		$title = $out->getTitle();
		if ( $title->isSpecial( 'Block' ) ) {
			$out->addJSConfigVars( [
				'wgCUDMaxAge' => $this->config->get( 'CUDMaxAge' )
			] );
		}

		$out->addModules( 'ext.checkUser.tempAccounts' );
		$out->addModuleStyles( 'ext.checkUser.styles' );
		$out->addJSConfigVars( [
			'wgCheckUserIsPerformerBlocked' => $permStatus->getBlock() !== null,
			'wgCheckUserTemporaryAccountMaxAge' => $this->config->get( 'CheckUserTemporaryAccountMaxAge' ),
			'wgCheckUserSpecialPagesWithoutIPRevealButtons' =>
				$this->config->get( 'CheckUserSpecialPagesWithoutIPRevealButtons' ),
		] );
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
		if ( !$this->config->get( 'CheckUserEnableTempAccountsOnboardingDialog' ) ) {
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
			'checkuser-temporary-account-no-preference', 'checkuser-temporary-account'
		) ) {
			return;
		}

		$userHasSeenDialog = $this->userOptionsLookup->getBoolOption(
			$out->getUser(), Preferences::TEMPORARY_ACCOUNTS_ONBOARDING_DIALOG_SEEN
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
