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

use MediaWiki\MediaWikiServices;
use Vector\Constants;
use Vector\Hooks;
use Vector\VectorServices;

/**
 * Skin subclass for Vector
 * @ingroup Skins
 * Skins extending SkinVector are not supported
 * @package Vector
 * @internal
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
		'class' => 'sticky-header-icon'
	];

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
	 * Whether or not the legacy version of the skin is being used.
	 *
	 * @return bool
	 */
	private function isLegacy(): bool {
		$isLatestSkinFeatureEnabled = MediaWikiServices::getInstance()
			->getService( Constants::SERVICE_FEATURE_MANAGER )
			->isFeatureEnabled( Constants::FEATURE_LATEST_SKIN );

		return !$isLatestSkinFeatureEnabled;
	}

	/**
	 * Overrides template, styles and scripts module when skin operates
	 * in legacy mode.
	 *
	 * @inheritDoc
	 * @param array|null $options Note; this param is only optional for internal purpose.
	 * 		Do not instantiate Vector, use SkinFactory to create the object instead.
	 * 		If you absolutely must to, this paramater is required; you have to provide the
	 * 		skinname with the `name` key. That's do it with `new SkinVector( ['name' => 'vector'] )`.
	 * 		Failure to do that, will lead to fatal exception.
	 */
	public function __construct( $options = [] ) {
		if ( $this->isLegacy() ) {
			$options['scripts'] = [ 'skins.vector.legacy.js' ];
			$options['styles'] = [ 'skins.vector.styles.legacy' ];
			$options['template'] = 'skin-legacy';
			unset( $options['link'] );
		}

		parent::__construct( $options );
	}

	/**
	 * Calls getLanguages with caching.
	 * @return array
	 */
	private function getLanguagesCached(): array {
		if ( $this->languages !== null ) {
			return $this->languages;
		}
		$this->languages = $this->getLanguages();
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
	 * @return bool
	 */
	private function isLanguagesInHeader() {
		$featureManager = VectorServices::getFeatureManager();
		// Disable button on pages without languages (based on Wikibase RepoItemLinkGenerator class)

		return $this->canHaveLanguages() && $featureManager->isFeatureEnabled(
			Constants::FEATURE_LANGUAGE_IN_HEADER
		);
	}

	/**
	 * If in modern Vector and no languages possible, OR the languages in header button
	 * is enabled but language array is empty, then we shouldn't show the langauge list.
	 * @return bool
	 */
	private function shouldHideLanguages() {
		return !$this->isLegacy() &&
			!$this->canHaveLanguages() ||
			// NOTE: T276950 - This should be revisited when an empty state for the language button is chosen.
			( $this->isLanguagesInHeader() && empty( $this->getLanguagesCached() ) );
	}

	/**
	 * Returns HTML for the create account button inside the anon user links
	 * @param string[] $returnto array of query strings used to build the login link
	 * @param string[] $class array of CSS classes to add.
	 * @param bool $includeIcon Set true to include icon CSS classes.
	 * @return string
	 */
	private function getCreateAccountHTML( $returnto, $class, $includeIcon ) {
		$createAccountData = $this->buildCreateAccountData( $returnto );
		$createAccountData['single-id'] = 'pt-createaccount';

		if ( $includeIcon ) {
			$class = array_merge(
				$class,
				[
					'mw-ui-icon mw-ui-icon-before',
					'mw-ui-icon-wikimedia-' . ( $createAccountData[ 'icon' ] ?? '' )
				]
			);
		}

		$createAccountData['class'] = $class;
		$htmlCreateAccount = $this->makeLink( 'create-account', $createAccountData );

		return $htmlCreateAccount;
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
			'mw-ui-icon mw-ui-icon-before',
			'mw-ui-icon-wikimedia-' . ( $loginData[ 'icon' ] ?? '' )
		];

		$learnMoreLinkData = [
			'text' => $this->msg( 'vector-anon-user-menu-pages-learn' )->text(),
			'href' => Title::newFromText( 'Help:Introduction' )->getLocalURL(),
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
			'mw-ui-icon mw-ui-icon-before',
			'mw-ui-icon-wikimedia-' . ( $logoutLinkData[ 'icon' ] ?? '' )
		];

		return $templateParser->processTemplate( 'UserLinks__logout', [
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
			'mw-ui-button',
			'mw-ui-quiet'
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
		] );
		$userMoreData = [
			"id" => 'p-personal-more',
			"class" => 'mw-portlet mw-portlet-personal-more vector-menu vector-user-menu-more',
			"html-items" => $userMoreHtmlItems,
			"label" => $this->msg( 'vector-personal-more-label' ),
			"heading-class" => 'vector-menu-heading',
			"is-dropdown" => false,
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
	 * Generate data needed to generate the sticky header.
	 * Lack of i18n is intentional and will be done as part of follow up work.
	 * @return array
	 */
	private function getStickyHeaderData() {
		return [
			'title' => 'Audre Lorde',
			'heading' => 'Introduction',
			'data-primary-action' => !$this->shouldHideLanguages() ? $this->getULSButtonData() : '',
			'data-button-start' => self::NO_ICON,
			'data-button-end' => self::NO_ICON,
			'data-buttons' => [
				self::NO_ICON, self::NO_ICON, self::NO_ICON, self::NO_ICON
			]
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getTemplateData(): array {
		$skin = $this;
		$out = $skin->getOutput();
		$title = $out->getTitle();
		$parentData = parent::getTemplateData();

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
			'is-article' => (bool)$out->isArticle(),

			'is-anon' => $this->getUser()->isAnon(),

			'is-mainpage' => $title->isMainPage(),
			// Remember that the string '0' is a valid title.
			// From OutputPage::getPageTitle, via ::setPageTitle().
			'html-title' => $out->getPageTitle(),

			'html-categories' => $skin->getCategories(),

			'input-location' => $this->getSearchBoxInputLocation(),

			'sidebar-visible' => $this->isSidebarVisible(),

			'is-language-in-header' => $this->isLanguagesInHeader(),

			'data-vector-sticky-header' => VectorServices::getFeatureManager()->isFeatureEnabled(
				Constants::FEATURE_STICKY_HEADER
			) ? $this->getStickyHeaderData() : false,
		] );

		if ( $skin->getUser()->isRegistered() ) {
			// Note: This data is also passed to legacy template where it is unused.
			$commonSkinData['data-emphasized-sidebar-action'] = [
				'href' => SpecialPage::getTitleFor(
					'Preferences',
					false,
					'mw-prefsection-rendering-skin-skin-prefs'
				)->getLinkURL( 'wprov=' . self::OPT_OUT_LINK_TRACKING_CODE ),
			];
		}

		if ( !$this->isLegacy() ) {
			$commonSkinData['data-vector-user-links'] = $this->getUserLinksTemplateData(
				$commonSkinData['data-portlets'],
				$commonSkinData['is-anon'],
				$commonSkinData['data-search-box']
			);
		}

		$commonSkinData['data-search-box'] = $this->getSearchData(
			$commonSkinData['data-search-box'],
			!$this->isLegacy()
		);

		return $commonSkinData;
	}

	/**
	 * Annotates search box with Vector-specific information
	 *
	 * @param array $searchBoxData
	 * @param bool $isCollapsible
	 * @return array modified version of $searchBoxData
	 */
	private function getSearchData( array $searchBoxData, bool $isCollapsible ) {
		$searchClass = 'vector-search-box';

		if ( $isCollapsible ) {
			$searchClass .= ' vector-search-box-collapses';
		}

		if ( $this->shouldSearchExpand() ) {
			$searchClass .= ' vector-search-box-show-thumbnail';
		}

		// Annotate search box with a component class.
		$searchBoxData['class'] = $searchClass;
		$searchBoxData['is-collapsible'] = $isCollapsible;

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
	 * Determines whether or not the search input should expand when focused
	 * before WVUI search is loaded. In WVUI, the search input expands to
	 * accomodate thumbnails in the suggestion list. When thumbnails are
	 * disabled, the input should not expand. Note this is only relevant for WVUI
	 * search (not legacy search).
	 *
	 * @return bool
	 */
	private function shouldSearchExpand(): bool {
		$featureManager = VectorServices::getFeatureManager();

		return $featureManager->isFeatureEnabled( Constants::FEATURE_USE_WVUI_SEARCH ) &&
			$this->getConfig()->get( 'VectorWvuiSearchOptions' )['showThumbnail'];
	}

	/**
	 * Determines wheather the initial state of sidebar is visible on not
	 *
	 * @return bool
	 */
	private function isSidebarVisible() {
		$skin = $this->getSkin();
		if ( $skin->getUser()->isRegistered() ) {
			$userPrefSidebarState = $skin->getUser()->getOption(
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
	 * Returns ULS button label
	 *
	 * @return string
	 */
	private function getULSLabel() {
		$label = $this->msg( 'vector-language-button-label' )
			->numParams( count( $this->getLanguagesCached() ) )
			->escaped();
		return $label;
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
			'label' => $this->getULSLabel(),
			'html-vector-button-icon' => Hooks::makeIcon( 'wikimedia-language' ),
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
			'label' => $this->getULSLabel(),
			// ext.uls.interface attaches click handler to this selector.
			'checkbox-class' => ' mw-interlanguage-selector ',
			'html-vector-heading-icon' => Hooks::makeIcon( 'wikimedia-language' ),
			'heading-class' =>
				' vector-menu-heading ' .
				' mw-ui-button mw-ui-quiet'
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
				$portletData['heading-class'] .= ' mw-ui-button mw-ui-quiet mw-ui-icon mw-ui-icon-element';
				$portletData['heading-class'] .= $this->loggedin ?
					' mw-ui-icon-wikimedia-userAvatar' :
					' mw-ui-icon-wikimedia-ellipsis';
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

		if ( $portletData['id'] === 'p-lang' && $this->isLanguagesInHeader() ) {
			$portletData = array_merge( $portletData, $this->getULSPortletData() );
		}
		$class = $portletData['class'];
		$portletData['class'] = trim( "$class $extraClasses[$type]" );
		return $portletData;
	}

	/**
	 * @inheritDoc
	 * @return array
	 */
	protected function getPortletData(
		$label,
		array $urls = []
	): array {
		switch ( $label ) {
			case 'user-menu':
			case 'actions':
			case 'variants':
				$type = self::MENU_TYPE_DROPDOWN;
				break;
			case 'views':
			case 'namespaces':
				$type = self::MENU_TYPE_TABS;
				break;
			case 'notifications':
			case 'personal':
			case 'user-page':
				$type = self::MENU_TYPE_DEFAULT;
				break;
			case 'lang':
				$type = $this->isLanguagesInHeader() ?
					self::MENU_TYPE_DROPDOWN : self::MENU_TYPE_PORTAL;
				break;
			default:
				$type = self::MENU_TYPE_PORTAL;
				break;
		}

		$portletData = $this->decoratePortletClass(
			parent::getPortletData( $label, $urls ),
			$type
		);

		// Special casing for Variant to change label to selected.
		// Hopefully we can revisit and possibly remove this code when the language switcher is moved.
		if ( $label === 'variants' ) {
			foreach ( $urls as $key => $item ) {
			// Check the class of the item for a `selected` class and if so, propagate the items
			// label to the main label.
				if ( isset( $item['class'] ) && stripos( $item['class'], 'selected' ) !== false ) {
					$portletData['label'] = $item['text'];
				}
			}
		}

		// T287494 We use tooltip messages to provide title attributes on hover over certain menu icons. For modern
		// Vector, the "tooltip-p-personal" key is set to "User menu" which is appropriate for the user icon (dropdown
		// indicator for user links menu) for logged-in users. This overrides the tooltip for the user links menu icon
		// which is an ellipsis for anonymous users.
		if ( $label === 'user-menu' && !$this->isLegacy() && !$this->loggedin ) {
			$portletData['html-tooltip'] = Linker::tooltip( 'vector-anon-user-menu-title' );
		}

		// Set tooltip to empty string for the personal menu for both logged-in and logged-out users to avoid showing
		// the tooltip for legacy version.
		if ( $label === 'personal' && $this->isLegacy() ) {
			$portletData['html-tooltip'] = '';
		}

		return $portletData + [
			'is-dropdown' => $type === self::MENU_TYPE_DROPDOWN,
		];
	}
}
