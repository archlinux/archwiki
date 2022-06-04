<?php
/**
 * Vector - Modern version of MonoBook with fresh look and many usability
 * improvements.
 *
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
 * @ingroup Skins
 */

namespace Vector;

use Action;
use ExtensionRegistry;
use Html;
use Linker;
use MediaWiki\MediaWikiServices;
use RuntimeException;
use SkinMustache;
use SkinTemplate;
use SpecialPage;
use Title;

/**
 * Skin subclass for Vector that may be the new or old version of Vector.
 *
 * @ingroup Skins
 * Skins extending SkinVector are not supported
 *
 * @package Vector
 * @internal
 *
 * # Migration Plan (please remove stages when done)
 *
 * Stage 1:
 * In future when we are ready to transition to two separate skins in this order:
 * - Use $wgSkipSkins to hide vector-2022.
 * - Remove skippable field from the `vector-2022` skin version. This will defer the code to the
 *   configuration option wgSkipSkins
 * - Set $wgVectorSkinMigrationMode = true and unset the Vector entry in wgSkipSkins
 * - for one wiki, to trial run. This will expose Vector in preferences. The new Vector will show
 *   as Vector (2022) to begin with and the skin version preference will be hidden.
 * - Check VectorPrefDiffInstrumentation instrumentation is still working.
 *
 * Stage 2:
 * - Set $wgVectorSkinMigrationMode = true for all wikis and update skin preference labels
 *   (See Iebe60b560069c8cfcdeed3f5986b8be35501dcbc). This will hide the skin version
 *    preference, and update the skin preference instead.
 * - We will set $wgDefaultSkin = 'vector-2022'; for desktop improvements wikis.
 *  - Run script that updates prefs table, migrating any rows where skin=vector AND
 *    skinversion = 2 to skin=vector22, skinversion=2
 *
 * Stage 3:
 *  - Move all modern code into SkinVector22.
 *  - Move legacy skin code from SkinVector to SkinVectorLegacy.
 *  - Update skin.json `vector` key to point to SkinVectorLegacy.
 *  - SkinVector left as alias if necessary.
 */
class SkinVector extends SkinMustache {
	/** @var null|array for caching purposes */
	private $languages;
	/** @var int */
	private const MENU_TYPE_DEFAULT = 0;
	/** @var int */
	private const MENU_TYPE_TABS = 1;
	/** @var int */
	private const MENU_TYPE_DROPDOWN = 2;
	private const MENU_TYPE_PORTAL = 3;
	private const NO_ICON = [
		'icon' => 'none',
		'is-quiet' => true,
		'tabindex' => '-1',
		'class' => 'sticky-header-icon'
	];
	private const TALK_ICON = [
		'href' => '#',
		'id' => 'ca-talk-sticky-header',
		'event' => 'talk-sticky-header',
		'icon' => 'wikimedia-speechBubbles',
		'is-quiet' => true,
		'tabindex' => '-1',
		'class' => 'sticky-header-icon'
	];
	private const HISTORY_ICON = [
		'href' => '#',
		'id' => 'ca-history-sticky-header',
		'event' => 'history-sticky-header',
		'icon' => 'wikimedia-history',
		'is-quiet' => true,
		'tabindex' => '-1',
		'class' => 'sticky-header-icon'
	];
	// Event and icon will be updated depending on watchstar state
	private const WATCHSTAR_ICON = [
		'href' => '#',
		'id' => 'ca-watchstar-sticky-header',
		'event' => 'watch-sticky-header',
		'icon' => 'wikimedia-star',
		'is-quiet' => true,
		'tabindex' => '-1',
		'class' => 'sticky-header-icon mw-watchlink'
	];
	private const EDIT_VE_ICON = [
		'href' => '#',
		'id' => 'ca-ve-edit-sticky-header',
		'event' => 've-edit-sticky-header',
		'icon' => 'wikimedia-edit',
		'is-quiet' => true,
		'tabindex' => '-1',
		'class' => 'sticky-header-icon'
	];
	private const EDIT_WIKITEXT_ICON = [
		'href' => '#',
		'id' => 'ca-edit-sticky-header',
		'event' => 'wikitext-edit-sticky-header',
		'icon' => 'wikimedia-wikiText',
		'is-quiet' => true,
		'tabindex' => '-1',
		'class' => 'sticky-header-icon'
	];
	private const EDIT_PROTECTED_ICON = [
		'href' => '#',
		'id' => 'ca-viewsource-sticky-header',
		'event' => 've-edit-protected-sticky-header',
		'icon' => 'wikimedia-editLock',
		'is-quiet' => true,
		'tabindex' => '-1',
		'class' => 'sticky-header-icon'
	];
	private const SEARCH_SHOW_THUMBNAIL_CLASS = 'vector-search-box-show-thumbnail';
	private const SEARCH_AUTO_EXPAND_WIDTH_CLASS = 'vector-search-box-auto-expand-width';
	private const STICKY_HEADER_ENABLED_CLASS = 'vector-sticky-header-enabled';
	private const TABLE_OF_CONTENTS_ENABLED_CLASS = 'vector-toc-enabled';
	private const CLASS_QUIET_BUTTON = 'mw-ui-button mw-ui-quiet';
	private const CLASS_PROGRESSIVE = 'mw-ui-progressive';
	private const CLASS_ICON_BUTTON = 'mw-ui-icon mw-ui-icon-element';
	private const CLASS_ICON_LABEL = 'mw-ui-icon mw-ui-icon-before';

	/**
	 * T243281: Code used to track clicks to opt-out link.
	 *
	 * The "vct" substring is used to describe the newest "Vector" (non-legacy)
	 * feature. The "w" describes the web platform. The "1" describes the version
	 * of the feature.
	 *
	 * @see https://wikitech.wikimedia.org/wiki/Provenance
	 * @var string
	 */
	private const OPT_OUT_LINK_TRACKING_CODE = 'vctw1';

	/**
	 * @param string $icon the name of the icon without wikimedia- prefix.
	 * @return string
	 */
	private function iconClass( $icon ) {
		if ( $icon ) {
			return 'mw-ui-icon-wikimedia-' . $icon;
		}
		return '';
	}

	/**
	 * Whether the legacy version of the skin is being used.
	 *
	 * @return bool
	 */
	protected function isLegacy(): bool {
		$options = $this->getOptions();
		if ( $options['name'] === Constants::SKIN_NAME_MODERN ) {
			return false;
		}
		$isLatestSkinFeatureEnabled = MediaWikiServices::getInstance()
			->getService( Constants::SERVICE_FEATURE_MANAGER )
			->isFeatureEnabled( Constants::FEATURE_LATEST_SKIN );

		return !$isLatestSkinFeatureEnabled;
	}

	/**
	 * Calls getLanguages with caching.
	 * @return array
	 */
	protected function getLanguagesCached(): array {
		if ( $this->languages === null ) {
			$this->languages = $this->getLanguages();
		}
		return $this->languages;
	}

	/**
	 * This should be upstreamed to the Skin class in core once the logic is finalized.
	 * Returns false if the page is a special page without any languages, or if an action
	 * other than view is being used.
	 * @return bool
	 */
	private function canHaveLanguages(): bool {
		$action = Action::getActionName( $this->getContext() );
		if ( $action !== 'view' ) {
			return false;
		}
		$title = $this->getTitle();
		// Defensive programming - if a special page has added languages explicitly, best to show it.
		if ( $title && $title->isSpecialPage() && empty( $this->getLanguagesCached() ) ) {
			return false;
		}
		return true;
	}

	/**
	 * @param string $location Either 'top' or 'bottom' is accepted.
	 * @return bool
	 */
	protected function isLanguagesInContentAt( $location ) {
		if ( !$this->canHaveLanguages() ) {
			return false;
		}
		$featureManager = VectorServices::getFeatureManager();
		$inContent = $featureManager->isFeatureEnabled(
			Constants::FEATURE_LANGUAGE_IN_HEADER
		);
		$isMainPage = $this->getTitle() ? $this->getTitle()->isMainPage() : false;

		switch ( $location ) {
			case 'top':
				return $isMainPage ? $inContent && $featureManager->isFeatureEnabled(
					Constants::FEATURE_LANGUAGE_IN_MAIN_PAGE_HEADER
				) : $inContent;
			case 'bottom':
				return $inContent && $isMainPage && !$featureManager->isFeatureEnabled(
					Constants::FEATURE_LANGUAGE_IN_MAIN_PAGE_HEADER
				);
			default:
				throw new RuntimeException( 'unknown language button location' );
		}
	}

	/**
	 * Whether or not the languages are out of the sidebar and in the content either at
	 * the top or the bottom.
	 * @return bool
	 */
	private function isLanguagesInContent() {
		return $this->isLanguagesInContentAt( 'top' ) || $this->isLanguagesInContentAt( 'bottom' );
	}

	/**
	 * Show the ULS button if it's modern Vector, languages in header is enabled,
	 * and the language array isn't empty. Hide it otherwise.
	 * @return bool
	 */
	protected function shouldHideLanguages() {
		// NOTE: T276950 - This should be revisited when an empty state for the language button is chosen.
		return $this->isLegacy() || !$this->isLanguagesInContent() || empty( $this->getLanguagesCached() );
	}

	/**
	 * Returns HTML for the create account link inside the anon user links
	 * @param string[] $returnto array of query strings used to build the login link
	 * @param string[] $class array of CSS classes to add.
	 * @param bool $includeIcon Set true to include icon CSS classes.
	 * @return string
	 */
	private function getCreateAccountHTML( $returnto, $class, $includeIcon ) {
		$createAccountData = $this->buildCreateAccountData( $returnto );
		$createAccountData['single-id'] = 'pt-createaccount';
		unset( $createAccountData['icon'] );

		if ( $includeIcon ) {
			$class = array_merge(
				$class,
				[
					self::CLASS_ICON_LABEL,
					$this->iconClass( $createAccountData[ 'icon' ] ?? '' )
				]
			);
		}

		$createAccountData['class'] = $class;
		return $this->makeLink( 'create-account', $createAccountData );
	}

	/**
	 * Returns HTML for the watchlist link inside user links
	 * @param array|null $watchlistMenuData (optional)
	 * @return string
	 */
	private function getWatchlistHTML( $watchlistMenuData = null ) {
		return $watchlistMenuData ? $watchlistMenuData['html-items'] : '';
	}

	/**
	 * Returns HTML for the create account button, login button and learn more link inside the anon user menu
	 * @param string[] $returnto array of query strings used to build the login link
	 * @param bool $useCombinedLoginLink if a combined login/signup link will be used
	 * @return string
	 */
	private function getAnonMenuBeforePortletHTML( $returnto, $useCombinedLoginLink ) {
		// 'single-id' must be provided for `makeLink` to populate `title`, `accesskey` and other attributes
		$loginData = $this->buildLoginData( $returnto, $useCombinedLoginLink );
		$loginData['single-id'] = 'pt-login';
		$loginData['class']  = [
			'vector-menu-content-item',
			'vector-menu-content-item-login',
			self::CLASS_ICON_LABEL,
			$this->iconClass( $loginData[ 'icon' ] ?? '' )
		];

		$learnMoreLinkData = [
			'text' => $this->msg( 'vector-anon-user-menu-pages-learn' )->text(),
			'href' => Title::newFromText( $this->msg( 'vector-intro-page' )->text() )->getLocalURL(),
			'aria-label' => $this->msg( 'vector-anon-user-menu-pages-label' )->text(),
		];
		$learnMoreLink = $this->makeLink( '', $learnMoreLinkData );

		$templateParser = $this->getTemplateParser();
		return $templateParser->processTemplate( 'UserLinks__login', [
			'htmlCreateAccount' => $this->getCreateAccountHTML( $returnto, [
				'user-links-collapsible-item',
				'vector-menu-content-item',
			], true ),
			'htmlLogin' => $this->makeLink( 'login', $loginData ),
			'msgLearnMore' => $this->msg( 'vector-anon-user-menu-pages' ),
			'htmlLearnMoreLink' => $learnMoreLink
		] );
	}

	/**
	 * Returns HTML for the logout button that should be placed in the user (personal) menu
	 * after the menu itself.
	 * @return string
	 */
	private function getLogoutHTML() {
		$logoutLinkData = $this->buildLogoutLinkData();
		$templateParser = $this->getTemplateParser();
		$logoutLinkData['class'] = [
			'vector-menu-content-item',
			'vector-menu-content-item-logout',
			self::CLASS_ICON_LABEL,
			$this->iconClass( $logoutLinkData[ 'icon' ] ?? '' )
		];

		return $templateParser->processTemplate( 'UserLinks__logout', [
			'msg-tooltip-pt-logout' => $this->msg( 'tooltip-pt-logout' ),
			'htmlLogout' => $this->makeLink( 'logout', $logoutLinkData )
		] );
	}

	/**
	 * Returns template data for UserLinks.mustache
	 * @param array $menuData existing menu template data to be transformed and copied for UserLinks
	 * @param bool $isAnon if the user is logged out, used to conditionally provide data
	 * @param array $searchBoxData representing search box.
	 * @return array
	 */
	private function getUserLinksTemplateData( $menuData, $isAnon, $searchBoxData ): array {
		$returnto = $this->getReturnToParam();
		$useCombinedLoginLink = $this->useCombinedLoginLink();
		$htmlCreateAccount = $this->getCreateAccountHTML( $returnto, [
			self::CLASS_QUIET_BUTTON
		], false );

		$templateParser = $this->getTemplateParser();
		// See T288428#7303233. The following conditional checks whether config is disabling account creation for
		// anonymous users in modern Vector. This check excludes the use case of extensions using core and legacy hooks
		// to remove the "Create account" link from the personal toolbar. Ideally this should be managed with a new hook
		// that tracks account creation ability.
		// Supporting removing items via hook involves unnecessary additional complexity we'd rather avoid at this time.
		// (see https://gerrit.wikimedia.org/r/c/mediawiki/skins/Vector/+/713505/3)
		// Account creation can be disabled by setting `$wgGroupPermissions['*']['createaccount'] = false;`
		$isCreateAccountAllowed = $isAnon && $this->getAuthority()->isAllowed( 'createaccount' );
		$userMoreHtmlItems = $templateParser->processTemplate( 'UserLinks__more', [
			'is-anon' => $isAnon,
			'is-create-account-allowed' => $isCreateAccountAllowed,
			'html-create-account' => $htmlCreateAccount,
			'data-user-interface-preferences' => $menuData[ 'data-user-interface-preferences' ],
			'data-notifications' => $menuData[ 'data-notifications' ],
			'data-user-page' => $menuData[ 'data-user-page' ],
			'html-vector-watchlist' => $this->getWatchlistHTML( $menuData[ 'data-vector-user-menu-overflow' ] ?? null ),
		] );
		$userMoreData = [
			'id' => 'p-personal-more',
			'class' => 'mw-portlet mw-portlet-personal-more vector-menu vector-user-menu-more',
			'html-items' => $userMoreHtmlItems,
			'label' => $this->msg( 'vector-personal-more-label' ),
			'heading-class' => 'vector-menu-heading',
			'is-dropdown' => false,
		];

		$userMenuData = $menuData[ 'data-user-menu' ];
		if ( $isAnon ) {
			$userMenuData[ 'html-before-portal' ] .= $this->getAnonMenuBeforePortletHTML(
				$returnto,
				$useCombinedLoginLink
			);
		} else {
			// Appending as to not override data potentially set by the onSkinAfterPortlet hook.
			$userMenuData[ 'html-after-portal' ] .= $this->getLogoutHTML();
		}

		return [
			'data-user-more' => $userMoreData,
			'data-user-menu' => $userMenuData
		];
	}

	/**
	 * @inheritDoc
	 */
	protected function runOnSkinTemplateNavigationHooks( SkinTemplate $skin, &$content_navigation ) {
		parent::runOnSkinTemplateNavigationHooks( $skin, $content_navigation );
		Hooks::onSkinTemplateNavigation( $skin, $content_navigation );
	}

	/**
	 * Updates modules for use in legacy Vector skin.
	 * Do not repeat this pattern. Will be addressed in T291098.
	 * @inheritDoc
	 */
	public function getDefaultModules() {
		// FIXME: Do not repeat this pattern. Will be addressed in T291098.
		if ( $this->isLegacy() ) {
			$this->options['scripts'] = SkinVectorLegacy::getScriptsOption();
			$this->options['styles'] = SkinVectorLegacy::getStylesOption();
		} else {
			$this->options['scripts'] = SkinVector22::getScriptsOption();
			$this->options['styles'] = SkinVector22::getStylesOption();
		}
		return parent::getDefaultModules();
	}

	/**
	 * Updates HTML generation for use in legacy Vector skin.
	 * Do not repeat this pattern. Will be addressed in T291098.
	 *
	 * @inheritDoc
	 */
	public function generateHTML() {
		if ( $this->isLegacy() ) {
			$this->options['template'] = SkinVectorLegacy::getTemplateOption();
		}
		return parent::generateHTML();
	}

	/**
	 * @inheritDoc
	 */
	public function getHtmlElementAttributes() {
		$original = parent::getHtmlElementAttributes();

		if ( VectorServices::getFeatureManager()->isFeatureEnabled( Constants::FEATURE_STICKY_HEADER ) ) {
			// T290518: Add scroll padding to root element when the sticky header is
			// enabled. This class needs to be server rendered instead of added from
			// JS in order to correctly handle situations where the sticky header
			// isn't visible yet but we still need scroll padding applied (e.g. when
			// the user navigates to a page with a hash fragment in the URI). For this
			// reason, we can't rely on the `vector-sticky-header-visible` class as it
			// is added too late.
			//
			// Please note that this class applies scroll padding which does not work
			// when applied to the body tag in Chrome, Safari, and Firefox (and
			// possibly others). It must instead be applied to the html tag.
			$original['class'] = implode( ' ', [ $original['class'] ?? '', self::STICKY_HEADER_ENABLED_CLASS ] );
		}

		if ( VectorServices::getFeatureManager()->isFeatureEnabled( Constants::FEATURE_TABLE_OF_CONTENTS ) ) {
			$original['class'] = implode( ' ', [ $original['class'] ?? '', self::TABLE_OF_CONTENTS_ENABLED_CLASS ] );
		}

		return $original;
	}

	/**
	 * Generate data needed to generate the sticky header.
	 * @param array $searchBoxData
	 * @param bool $includeEditIcons
	 * @return array
	 */
	private function getStickyHeaderData( $searchBoxData, $includeEditIcons ): array {
		$btns = [
			self::TALK_ICON,
			self::HISTORY_ICON,
			self::WATCHSTAR_ICON,
		];
		if ( $includeEditIcons ) {
			$btns[] = self::EDIT_WIKITEXT_ICON;
			$btns[] = self::EDIT_PROTECTED_ICON;
			$btns[] = self::EDIT_VE_ICON;
		}

		// Show sticky ULS if the ULS extension is enabled and the ULS in header is not hidden
		$showStickyULS = ExtensionRegistry::getInstance()->isLoaded( 'UniversalLanguageSelector' )
			&& !$this->shouldHideLanguages();
		return [
			'data-primary-action' => $showStickyULS ?
				$this->getULSButtonData() : null,
			'data-button-start' => [
				'label' => $this->msg( 'search' ),
				'icon' => 'wikimedia-search',
				'is-quiet' => true,
				'tabindex' => '-1',
				'class' => 'vector-sticky-header-search-toggle',
				'event' => 'ui.' . $searchBoxData['form-id'] . '.icon'
			],
			'data-search' => $searchBoxData,
			'data-buttons' => $btns,
		];
	}

	/**
	 * Generate data needed to create SidebarAction item.
	 * @param array $htmlData data to make a link or raw html
	 * @param array $headingOptions optional heading for the html
	 * @return array keyed data for the SidebarAction template
	 */
	private function makeSidebarActionData( array $htmlData = [], array $headingOptions = [] ): array {
		$htmlContent = '';
		// Populates the sidebar as a standalone link or custom html.
		if ( array_key_exists( 'link', $htmlData ) ) {
			$htmlContent = $this->makeLink( 'link', $htmlData['link'] );
		} elseif ( array_key_exists( 'html-content', $htmlData ) ) {
			$htmlContent = $htmlData['html-content'];
		}

		return $headingOptions + [
			'html-content' => $htmlContent,
		];
	}

	/**
	 * Determines if the language switching alert box should be in the sidebar.
	 *
	 * @return bool
	 */
	private function shouldLanguageAlertBeInSidebar(): bool {
		$featureManager = VectorServices::getFeatureManager();
		$isMainPage = $this->getTitle() ? $this->getTitle()->isMainPage() : false;
		$shouldShowOnMainPage = $isMainPage && !empty( $this->getLanguagesCached() ) &&
			$featureManager->isFeatureEnabled( Constants::FEATURE_LANGUAGE_IN_MAIN_PAGE_HEADER );
		return ( $this->isLanguagesInContentAt( 'top' ) && !$isMainPage && !$this->shouldHideLanguages() &&
			$featureManager->isFeatureEnabled( Constants::FEATURE_LANGUAGE_ALERT_IN_SIDEBAR ) ) ||
			$shouldShowOnMainPage;
	}

	/**
	 * @inheritDoc
	 */
	public function getTemplateData(): array {
		$skin = $this;
		$out = $skin->getOutput();
		$parentData = $this->decoratePortletsData( parent::getTemplateData() );
		$featureManager = VectorServices::getFeatureManager();

		// SkinVector sometimes serves new Vector as part of removing the
		// skin version user preference. TCho avoid T302461 we need to unset it here.
		// This shouldn't be run on SkinVector22.
		if ( $this->getSkinName() === 'vector' ) {
			unset( $parentData['data-toc'] );
		}

		//
		// Naming conventions for Mustache parameters.
		//
		// Value type (first segment):
		// - Prefix "is" or "has" for boolean values.
		// - Prefix "msg-" for interface message text.
		// - Prefix "html-" for raw HTML.
		// - Prefix "data-" for an array of template parameters that should be passed directly
		//   to a template partial.
		// - Prefix "array-" for lists of any values.
		//
		// Source of value (first or second segment)
		// - Segment "page-" for data relating to the current page (e.g. Title, WikiPage, or OutputPage).
		// - Segment "hook-" for any thing generated from a hook.
		//   It should be followed by the name of the hook in hyphenated lowercase.
		//
		// Conditionally used values must use null to indicate absence (not false or '').
		$commonSkinData = array_merge( $parentData, [
			'is-legacy' => $this->isLegacy(),

			'input-location' => $this->getSearchBoxInputLocation(),

			'sidebar-visible' => $this->isSidebarVisible(),

			'is-language-in-content' => $this->isLanguagesInContent(),
			'is-language-in-content-top' => $this->isLanguagesInContentAt( 'top' ),
			'is-language-in-content-bottom' => $this->isLanguagesInContentAt( 'bottom' ),
			'data-search-box' => $this->getSearchData(
				$parentData['data-search-box'],
				!$this->isLegacy(),
				// is primary mode of search
				true,
				'searchform',
				true
			),
			'data-vector-sticky-header' => $featureManager->isFeatureEnabled(
				Constants::FEATURE_STICKY_HEADER
			) ? $this->getStickyHeaderData(
				$this->getSearchData(
					$parentData['data-search-box'],
					// Collapse inside search box is disabled.
					false,
					false,
					'vector-sticky-search-form',
					false
				),
				$featureManager->isFeatureEnabled(
					Constants::FEATURE_STICKY_HEADER_EDIT
				)
			) : false,
			'data-toc' => $this->getTocData( $parentData['data-toc'] ?? [] )
		] );

		if ( $skin->getUser()->isRegistered() ) {
			$migrationMode = $this->getConfig()->get( 'VectorSkinMigrationMode' );
			$query = $migrationMode ? 'useskin=vector&' : '';
			// Note: This data is also passed to legacy template where it is unused.
			$optOutUrl = [
				'text' => $this->msg( 'vector-opt-out' )->text(),
				'href' => SpecialPage::getTitleFor(
					'Preferences',
					false,
					$migrationMode ? 'mw-prefsection-rendering-skin' : 'mw-prefsection-rendering-skin-skin-prefs'
				)->getLinkURL( $query . 'wprov=' . self::OPT_OUT_LINK_TRACKING_CODE ),
				'title' => $this->msg( 'vector-opt-out-tooltip' )->text(),
				'active' => false,
			];
			$htmlData = [
				'link' => $optOutUrl,
			];
			$commonSkinData['data-emphasized-sidebar-action'][] = $this->makeSidebarActionData(
				$htmlData,
				[]
			);
		}

		if ( !$this->isLegacy() ) {
			$commonSkinData['data-vector-user-links'] = $this->getUserLinksTemplateData(
				$commonSkinData['data-portlets'],
				$commonSkinData['is-anon'],
				$commonSkinData['data-search-box']
			);

			// T295555 Add language switch alert message temporarily (to be removed).
			if ( $this->shouldLanguageAlertBeInSidebar() ) {
				$languageSwitchAlert = [
					'html-content' => Html::noticeBox(
						$this->msg( 'vector-language-redirect-to-top' )->parse(),
						'vector-language-sidebar-alert'
					),
				];
				$headingOptions = [
					'heading' => $this->msg( 'vector-languages' )->plain(),
				];
				$commonSkinData['data-vector-language-switch-alert'][] = $this->makeSidebarActionData(
					$languageSwitchAlert,
					$headingOptions
				);
			}
		}

		return $commonSkinData;
	}

	/**
	 * Annotates table of contents data with Vector-specific information.
	 *
	 * @param array $tocData
	 * @return array
	 */
	private function getTocData( array $tocData ): array {
		if ( empty( $tocData ) ) {
			return [];
		}

		return array_merge( $tocData, [
			'vector-is-collapse-sections-enabled' =>
				$tocData[ 'number-section-count'] >= $this->getConfig()->get(
					'VectorTableOfContentsCollapseAtCount'
				)
		] );
	}

	/**
	 * Annotates search box with Vector-specific information
	 *
	 * @param array $searchBoxData
	 * @param bool $isCollapsible
	 * @param bool $isPrimary
	 * @param string $formId
	 * @param bool $autoExpandWidth
	 * @return array modified version of $searchBoxData
	 */
	private function getSearchData(
		array $searchBoxData,
		bool $isCollapsible,
		bool $isPrimary,
		string $formId,
		bool $autoExpandWidth
	) {
		$searchClass = 'vector-search-box-vue ';

		if ( $isCollapsible ) {
			$searchClass .= ' vector-search-box-collapses ';
		}

		if ( $this->doesSearchHaveThumbnails() ) {
			$searchClass .= ' ' . self::SEARCH_SHOW_THUMBNAIL_CLASS .
				( $autoExpandWidth ? ' ' . self::SEARCH_AUTO_EXPAND_WIDTH_CLASS : '' );
		}

		// Annotate search box with a component class.
		$searchBoxData['class'] = trim( $searchClass );
		$searchBoxData['is-collapsible'] = $isCollapsible;
		$searchBoxData['is-primary'] = $isPrimary;
		$searchBoxData['form-id'] = $formId;

		// At lower resolutions the search input is hidden search and only the submit button is shown.
		// It should behave like a form submit link (e.g. submit the form with no input value).
		// We'll wire this up in a later task T284242.
		$searchBoxData['data-collapse-icon'] = [
			'href' => Title::newFromText( $searchBoxData['page-title'] )->getLocalUrl(),
			'label' => $this->msg( 'search' ),
			'icon' => 'wikimedia-search',
			'is-quiet' => true,
			'class' => 'search-toggle',
		];

		return $searchBoxData;
	}

	/**
	 * Gets the value of the "input-location" parameter for the SearchBox Mustache template.
	 *
	 * @return string Either `Constants::SEARCH_BOX_INPUT_LOCATION_DEFAULT` or
	 *  `Constants::SEARCH_BOX_INPUT_LOCATION_MOVED`
	 */
	private function getSearchBoxInputLocation(): string {
		if ( $this->isLegacy() ) {
			return Constants::SEARCH_BOX_INPUT_LOCATION_DEFAULT;
		}

		return Constants::SEARCH_BOX_INPUT_LOCATION_MOVED;
	}

	/**
	 * @inheritDoc
	 */
	public function isResponsive() {
		// Check it's enabled by user preference and configuration
		$responsive = parent::isResponsive() && $this->getConfig()->get( 'VectorResponsive' );
		// For historic reasons, the viewport is added when Vector is loaded on the mobile
		// domain. This is only possible for 3rd parties or by useskin parameter as there is
		// no preference for changing mobile skin. Only need to check if $responsive is falsey.
		if ( !$responsive && ExtensionRegistry::getInstance()->isLoaded( 'MobileFrontend' ) ) {
			$mobFrontContext = MediaWikiServices::getInstance()->getService( 'MobileFrontend.Context' );
			if ( $mobFrontContext->shouldDisplayMobileView() ) {
				return true;
			}
		}
		return $responsive;
	}

	/**
	 * Returns `true` if WVUI is enabled to show thumbnails and `false` otherwise.
	 * Note this is only relevant for WVUI search (not legacy search).
	 *
	 * @return bool
	 */
	private function doesSearchHaveThumbnails(): bool {
		return $this->getConfig()->get( 'VectorWvuiSearchOptions' )['showThumbnail'];
	}

	/**
	 * Determines wheather the initial state of sidebar is visible on not
	 *
	 * @return bool
	 */
	private function isSidebarVisible() {
		$skin = $this->getSkin();
		if ( $skin->getUser()->isRegistered() ) {
			$userOptionsLookup = MediaWikiServices::getInstance()->getUserOptionsLookup();
			$userPrefSidebarState = $userOptionsLookup->getOption(
				$skin->getUser(),
				Constants::PREF_KEY_SIDEBAR_VISIBLE
			);

			$defaultLoggedinSidebarState = $this->getConfig()->get(
				Constants::CONFIG_KEY_DEFAULT_SIDEBAR_VISIBLE_FOR_AUTHORISED_USER
			);

			// If the sidebar user preference has been set, return that value,
			// if not, then the default sidebar state for logged-in users.
			return ( $userPrefSidebarState !== null )
				? (bool)$userPrefSidebarState
				: $defaultLoggedinSidebarState;
		}
		return $this->getConfig()->get(
			Constants::CONFIG_KEY_DEFAULT_SIDEBAR_VISIBLE_FOR_ANONYMOUS_USER
		);
	}

	/**
	 * Returns ULS button label within the context of the translated message taking a placeholder.
	 *
	 * @param string $message
	 * @return string
	 */
	private function getULSLabel( string $message ) {
		return $this->msg( $message )
			->numParams( count( $this->getLanguagesCached() ) )
			->escaped();
	}

	/**
	 * Creates button data for the ULS button in the sticky header
	 *
	 * @return array
	 */
	private function getULSButtonData() {
		return [
			'id' => 'p-lang-btn-sticky-header',
			'class' => 'mw-interlanguage-selector',
			'is-quiet' => true,
			'tabindex' => '-1',
			'label' => $this->getULSLabel( 'vector-language-button-label' ),
			'html-vector-button-icon' => Hooks::makeIcon( 'wikimedia-language' ),
			'event' => 'ui.dropdown-p-lang-btn-sticky-header'
		];
	}

	/**
	 * Creates portlet data for the ULS button in the header
	 *
	 * @return array
	 */
	private function getULSPortletData() {
		$languageButtonData = [
			'id' => 'p-lang-btn',
			'label' => $this->getULSLabel( 'vector-language-button-label' ),
			'aria-label' => $this->getULSLabel( 'vector-language-button-aria-label' ),
			// ext.uls.interface attaches click handler to this selector.
			'checkbox-class' => ' mw-interlanguage-selector ',
			'html-vector-heading-icon' => Hooks::makeIcon( 'wikimedia-language-progressive' ),
			'heading-class' =>
				' vector-menu-heading ' .
				self::CLASS_QUIET_BUTTON . ' ' .
				self::CLASS_PROGRESSIVE
			];

		// Adds class to hide language button
		// Temporary solution to T287206, can be removed when ULS dialog includes interwiki links
		if ( $this->shouldHideLanguages() ) {
			$languageButtonData['class'] = ' mw-portlet-empty';
		}

		return $languageButtonData;
	}

	/**
	 * helper for applying Vector menu classes to portlets
	 *
	 * @param array $portletData returned by SkinMustache to decorate
	 * @param int $type representing one of the menu types (see MENU_TYPE_* constants)
	 * @return array modified version of portletData input
	 */
	private function decoratePortletClass(
		array $portletData,
		int $type = self::MENU_TYPE_DEFAULT
	) {
		$extraClasses = [
			self::MENU_TYPE_DROPDOWN => 'vector-menu vector-menu-dropdown',
			self::MENU_TYPE_TABS => 'vector-menu vector-menu-tabs',
			self::MENU_TYPE_PORTAL => 'vector-menu vector-menu-portal portal',
			self::MENU_TYPE_DEFAULT => 'vector-menu',
		];
		$portletData['heading-class'] = 'vector-menu-heading';
		// Add target class to apply different icon to personal menu dropdown for logged in users.
		if ( $portletData['id'] === 'p-personal' ) {
			if ( $this->isLegacy() ) {
				$portletData['class'] .= ' vector-user-menu-legacy';
			} else {
				$portletData['class'] .= ' vector-user-menu';
				$portletData['class'] .= $this->loggedin ?
					' vector-user-menu-logged-in' :
					' vector-user-menu-logged-out';
				$portletData['heading-class'] .= ' ' . self::CLASS_QUIET_BUTTON . ' ' .
					self::CLASS_ICON_BUTTON . ' ';
				$portletData['heading-class'] .= $this->loggedin ?
					$this->iconClass( 'userAvatar' ) :
					$this->iconClass( 'ellipsis' );
			}
		}
		switch ( $portletData['id'] ) {
			case 'p-variants':
			case 'p-cactions':
				$portletData['class'] .= ' vector-menu-dropdown-noicon';
				break;
			default:
				break;
		}

		if ( $portletData['id'] === 'p-lang' && $this->isLanguagesInContent() ) {
			$portletData = array_merge( $portletData, $this->getULSPortletData() );
		}
		$class = $portletData['class'];
		$portletData['class'] = trim( "$class $extraClasses[$type]" );
		return $portletData;
	}

	/**
	 * Performs updates to all portlets.
	 *
	 * @param array $data
	 * @return array
	 */
	private function decoratePortletsData( array $data ) {
		foreach ( $data['data-portlets'] as $key => $pData ) {
			$data['data-portlets'][$key] = $this->decoratePortletData(
				$key,
				$pData
			);
		}
		$sidebar = $data['data-portlets-sidebar'];
		$sidebar['data-portlets-first'] = $this->decoratePortletData(
			'navigation', $sidebar['data-portlets-first']
		);
		$rest = $sidebar['array-portlets-rest'];
		foreach ( $rest as $key => $pData ) {
			$rest[$key] = $this->decoratePortletData(
				$pData['id'], $pData
			);
		}
		$sidebar['array-portlets-rest'] = $rest;
		$data['data-portlets-sidebar'] = $sidebar;
		return $data;
	}

	/**
	 * Performs the following updates to portlet data:
	 * - Adds concept of menu types
	 * - Marks the selected variant in the variant portlet
	 * - modifies tooltips of personal and user-menu portlets
	 * @param string $key
	 * @param array $portletData
	 * @return array
	 */
	private function decoratePortletData(
		string $key,
		array $portletData
	): array {
		switch ( $key ) {
			case 'data-user-menu':
			case 'data-actions':
			case 'data-variants':
				$type = self::MENU_TYPE_DROPDOWN;
				break;
			case 'data-views':
			case 'data-namespaces':
				$type = self::MENU_TYPE_TABS;
				break;
			case 'data-notifications':
			case 'data-personal':
			case 'data-user-page':
				$type = self::MENU_TYPE_DEFAULT;
				break;
			case 'data-languages':
				$type = $this->isLanguagesInContent() ?
					self::MENU_TYPE_DROPDOWN : self::MENU_TYPE_PORTAL;
				break;
			default:
				$type = self::MENU_TYPE_PORTAL;
				break;
		}

		$portletData = $this->decoratePortletClass(
			$portletData,
			$type
		);

		// Special casing for Variant to change label to selected.
		// Hopefully we can revisit and possibly remove this code when the language switcher is moved.
		if ( $key === 'data-variants' ) {
			$languageConverterFactory = MediaWikiServices::getInstance()->getLanguageConverterFactory();
			$pageLang = $this->getTitle()->getPageLanguage();
			$converter = $languageConverterFactory->getLanguageConverter( $pageLang );
			$portletData['label'] = $pageLang->getVariantname(
				$converter->getPreferredVariant()
			);
			// T289523 Add aria-label data to the language variant switcher.
			$portletData['aria-label'] = $this->msg( 'vector-language-variant-switcher-label' );
		}

		// T287494 We use tooltip messages to provide title attributes on hover over certain menu icons. For modern
		// Vector, the "tooltip-p-personal" key is set to "User menu" which is appropriate for the user icon (dropdown
		// indicator for user links menu) for logged-in users. This overrides the tooltip for the user links menu icon
		// which is an ellipsis for anonymous users.
		if ( $key === 'data-user-menu' && !$this->isLegacy() && !$this->loggedin ) {
			$portletData['html-tooltip'] = Linker::tooltip( 'vector-anon-user-menu-title' );
		}

		// Set tooltip to empty string for the personal menu for both logged-in and logged-out users to avoid showing
		// the tooltip for legacy version.
		if ( $key === 'data-personal' && $this->isLegacy() ) {
			$portletData['html-tooltip'] = '';
		}

		return $portletData + [
			'is-dropdown' => $type === self::MENU_TYPE_DROPDOWN,
		];
	}
}

class_alias( SkinVector::class, 'SkinVector' );
