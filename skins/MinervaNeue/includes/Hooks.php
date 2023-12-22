<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace MediaWiki\Minerva;

use ChangesList;
use ChangesListFilterGroup;
use Config;
use ExtensionRegistry;
use Html;
use MediaWiki\Hook\FetchChangesListHook;
use MediaWiki\Hook\OutputPageBodyAttributesHook;
use MediaWiki\Hook\UserLogoutCompleteHook;
use MediaWiki\MediaWikiServices;
use MediaWiki\Minerva\Hooks\HookRunner;
use MediaWiki\Minerva\Skins\SkinMinerva;
use MediaWiki\Minerva\Skins\SkinUserPageHelper;
use MediaWiki\ResourceLoader\Context;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderGetConfigVarsHook;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderRegisterModulesHook;
use MediaWiki\ResourceLoader\ResourceLoader;
use MediaWiki\Skins\Hook\SkinPageReadyConfigHook;
use MediaWiki\SpecialPage\Hook\SpecialPageBeforeExecuteHook;
use MobileContext;
use OldChangesList;
use OutputPage;
use Skin;
use SpecialPage;
use User;
use Wikimedia\Services\NoSuchServiceException;

/**
 * Hook handlers for Minerva skin.
 *
 * Hook handler method names should be in the form of:
 *	on<HookName>()
 */
class Hooks implements
	FetchChangesListHook,
	OutputPageBodyAttributesHook,
	ResourceLoaderGetConfigVarsHook,
	ResourceLoaderRegisterModulesHook,
	SkinPageReadyConfigHook,
	SpecialPageBeforeExecuteHook,
	UserLogoutCompleteHook
{
	public const FEATURE_OVERFLOW_PAGE_ACTIONS = 'MinervaOverflowInPageActions';

	/**
	 * ResourceLoaderRegisterModules hook handler.
	 *
	 * Registers:
	 *
	 * * EventLogging schema modules, if the EventLogging extension is loaded;
	 * * Modules for the Visual Editor overlay, if the VisualEditor extension is loaded; and
	 * * Modules for the notifications overlay, if the Echo extension is loaded.
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ResourceLoaderRegisterModules
	 *
	 * @param ResourceLoader $resourceLoader
	 */
	public function onResourceLoaderRegisterModules( ResourceLoader $resourceLoader ): void {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'MobileFrontend' ) ) {
			$resourceLoader->register( [
				'mobile.startup' => [
					'dependencies' => [ 'mediawiki.searchSuggest' ],
					'localBasePath' => dirname( __DIR__ ),
					'remoteExtPath' => 'Minerva',
					'scripts' => 'resources/mobile.startup.stub.js',
				]
			] );
		}
	}

	/**
	 * PreferencesGetLayout hook handler.
	 *
	 * Use mobile layout in Special:Preferences
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PreferencesGetLayout
	 *
	 * @param bool &$useMobileLayout
	 * @param Skin|string $skin
	 */
	public static function onPreferencesGetLayout( &$useMobileLayout, $skin ) {
		if ( $skin instanceof Skin && $skin->getSkinName() === 'minerva' ) {
			$useMobileLayout = true;
		} elseif ( is_string( $skin ) && $skin === 'minerva' ) {
			$useMobileLayout = true;
		}
	}

	/**
	 * Disable recent changes enhanced mode (table mode)
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/FetchChangesList
	 *
	 * @param User $user
	 * @param Skin $skin
	 * @param ChangesList|null &$list
	 * @param ChangesListFilterGroup[] $groups
	 * @return bool|null
	 */
	public function onFetchChangesList( $user, $skin, &$list, $groups ) {
		if ( $skin->getSkinName() === 'minerva' ) {
			// The new changes list (table-based) does not work with Minerva
			$list = new OldChangesList( $skin->getContext(), $groups );
			// returning false makes sure $list is used instead.
			return false;
		}
	}

	/**
	 * Invocation of hook SpecialPageBeforeExecute
	 *
	 * We use this hook to ensure that login/account creation pages
	 * are redirected to HTTPS if they are not accessed via HTTPS and
	 * $wgSecureLogin == true - but only when using the
	 * mobile site.
	 *
	 * @param SpecialPage $special
	 * @param string $subpage
	 */
	public function onSpecialPageBeforeExecute( $special, $subpage ) {
		$name = $special->getName();
		$out = $special->getOutput();
		$skin = $out->getSkin();
		$request = $special->getRequest();

		if ( $skin instanceof SkinMinerva ) {
			switch ( $name ) {
				case 'Recentchanges':
					$isEnhancedDefaultForUser = MediaWikiServices::getInstance()
						->getUserOptionsLookup()
						->getBoolOption( $special->getUser(), 'usenewrc' );
					$enhanced = $request->getBool( 'enhanced', $isEnhancedDefaultForUser );
					if ( $enhanced ) {
						$out->addHTML( Html::warningBox(
							$special->msg( 'skin-minerva-recentchanges-warning-enhanced-not-supported' )->parse()
						) );
					}
					break;
				case 'Userlogin':
				case 'CreateAccount':
					// Add default warning message to Special:UserLogin and Special:UserCreate
					// if no warning message set.
					if (
						!$request->getVal( 'warning' ) &&
						!$special->getUser()->isRegistered() &&
						!$request->wasPosted()
					) {
						$request->setVal( 'warning', 'mobile-frontend-generic-login-new' );
					}
					break;
			}
		}
	}

	/**
	 * Set the skin options for Minerva
	 *
	 * @param MobileContext $mobileContext
	 * @param Skin $skin
	 */
	public static function setMinervaSkinOptions(
		MobileContext $mobileContext, Skin $skin
	) {
		// setSkinOptions is not available
		if ( $skin instanceof SkinMinerva ) {
			$services = MediaWikiServices::getInstance();
			$featureManager = $services
				->getService( 'MobileFrontend.FeaturesManager' );
			$skinOptions = $services->getService( 'Minerva.SkinOptions' );
			$title = $skin->getTitle();

			// T245162 - this should only apply if the context relates to a page view.
			// Examples:
			// - parsing wikitext during an REST response
			// - a ResourceLoader response
			if ( $title !== null ) {
				// T232653: TALK_AT_TOP, HISTORY_IN_PAGE_ACTIONS, TOOLBAR_SUBMENU should
				// be true on user pages and user talk pages for all users
				//
				// For some reason using $services->getService( 'SkinUserPageHelper' )
				// here results in a circular dependency error which is why
				// SkinUserPageHelper is being instantiated instead.
				$relevantUserPageHelper = new SkinUserPageHelper(
					$services->getUserNameUtils(),
					$services->getUserFactory(),
					$title->inNamespace( NS_USER_TALK ) ? $title->getSubjectPage() : $title,
					$mobileContext
				);

				$isUserPage = $relevantUserPageHelper->isUserPage();
				$isUserPageAccessible = $relevantUserPageHelper->isUserPageAccessibleToCurrentUser();
				$isUserPageOrUserTalkPage = $isUserPage && $isUserPageAccessible;
			} else {
				// If no title this must be false
				$isUserPageOrUserTalkPage = false;
			}

			$isBeta = $mobileContext->isBetaGroupMember();
			$skinOptions->setMultiple( [
				SkinOptions::SHOW_DONATE => $featureManager->isFeatureAvailableForCurrentUser( 'MinervaDonateLink' ),
				SkinOptions::TALK_AT_TOP => $isUserPageOrUserTalkPage ?
					true : $featureManager->isFeatureAvailableForCurrentUser( 'MinervaTalkAtTop' ),
				SkinOptions::BETA_MODE
					=> $isBeta,
				SkinOptions::CATEGORIES
					=> $featureManager->isFeatureAvailableForCurrentUser( 'MinervaShowCategories' ),
				SkinOptions::PAGE_ISSUES
					=> $featureManager->isFeatureAvailableForCurrentUser( 'MinervaPageIssuesNewTreatment' ),
				SkinOptions::MOBILE_OPTIONS => true,
				SkinOptions::PERSONAL_MENU => $featureManager->isFeatureAvailableForCurrentUser(
					'MinervaPersonalMenu'
				),
				SkinOptions::MAIN_MENU_EXPANDED => $featureManager->isFeatureAvailableForCurrentUser(
					'MinervaAdvancedMainMenu'
				),
				// In mobile, always resort to single icon.
				SkinOptions::SINGLE_ECHO_BUTTON => true,
				SkinOptions::HISTORY_IN_PAGE_ACTIONS => $isUserPageOrUserTalkPage ?
					true : $featureManager->isFeatureAvailableForCurrentUser( 'MinervaHistoryInPageActions' ),
				SkinOptions::TOOLBAR_SUBMENU => $isUserPageOrUserTalkPage ?
					true : $featureManager->isFeatureAvailableForCurrentUser(
						self::FEATURE_OVERFLOW_PAGE_ACTIONS
					),
				SkinOptions::TABS_ON_SPECIALS => true,
			] );
			( new HookRunner( $services->getHookContainer() ) )->onSkinMinervaOptionsInit( $skin, $skinOptions );
		}
	}

	/**
	 * UserLogoutComplete hook handler.
	 * Resets skin options if a user logout occurs - this is necessary as the
	 * RequestContextCreateSkinMobile hook runs before the UserLogout hook.
	 *
	 * @param User $user
	 * @param string &$inject_html
	 * @param string $oldName
	 */
	public function onUserLogoutComplete( $user, &$inject_html, $oldName ) {
		try {
			$ctx = MediaWikiServices::getInstance()->getService( 'MobileFrontend.Context' );
			self::setMinervaSkinOptions( $ctx, $ctx->getSkin() );
		} catch ( NoSuchServiceException $ex ) {
			// MobileFrontend not installed. Not important.
		}
	}

	/**
	 * ResourceLoaderGetConfigVars hook handler.
	 * Used for setting JS variables which are pulled in dynamically with RL
	 * instead of embedded directly on the page with a script tag.
	 * These vars have a shorter cache-life than those in `getJsConfigVars`.
	 *
	 * @param array &$vars Array of variables to be added into the output of the RL startup module.
	 * @param string $skin
	 * @param Config $config
	 */
	public function onResourceLoaderGetConfigVars( array &$vars, $skin, Config $config ): void {
		if ( $skin === 'minerva' ) {
			// This is to let the UI adjust itself to a wiki that is always read-only.
			// Ignore temporary read-only on live wikis, requires heavy DB check (T233458).
			$roConf = MediaWikiServices::getInstance()->getConfiguredReadOnlyMode();
			$vars += [
				'wgMinervaABSamplingRate' => $config->get( 'MinervaABSamplingRate' ),
				'wgMinervaReadOnly' => $roConf->isReadOnly(),
			];
		}
	}

	/**
	 * Modifies the `<body>` element's attributes.
	 *
	 * By default, the `class` attribute is set to the output's "bodyClassName"
	 * property.
	 *
	 * @param OutputPage $out
	 * @param Skin $skin
	 * @param string[] &$bodyAttrs
	 */
	public function onOutputPageBodyAttributes( $out, $skin, &$bodyAttrs ): void {
		$classes = $out->getProperty( 'bodyClassName' );
		$skinOptions = MediaWikiServices::getInstance()->getService( 'Minerva.SkinOptions' );
		$isMinerva = $skin instanceof SkinMinerva;

		if ( $isMinerva && $skinOptions->get( SkinOptions::HISTORY_IN_PAGE_ACTIONS ) ) {
			// Class is used when page actions is modified to contain more elements
			$classes .= ' minerva--history-page-action-enabled';
		}

		if ( $isMinerva ) {
			$bodyAttrs['class'] .= ' ' . $classes;
		}
	}

	/**
	 * SkinPageReadyConfig hook handler
	 *
	 * Disable collapsible on page load
	 *
	 * @param Context $context
	 * @param mixed[] &$config Associative array of configurable options
	 */
	public function onSkinPageReadyConfig(
		Context $context,
		array &$config
	): void {
		if ( $context->getSkin() === 'minerva' ) {
			$config['search'] = false;
			$config['collapsible'] = false;
			$config['selectorLogoutLink'] = 'a.menu__item--logout[data-mw="interface"]';
		}
	}
}
