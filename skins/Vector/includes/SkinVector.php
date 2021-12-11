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
	private function isLegacy() : bool {
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
		} else {
			// For historic reasons, the viewport is added when Vector is loaded on the mobile
			// domain. This is only possible for 3rd parties or by useskin parameter as there is
			// no preference for changing mobile skin.
			$responsive = $this->getConfig()->get( 'VectorResponsive' );
			if ( ExtensionRegistry::getInstance()->isLoaded( 'MobileFrontend' ) ) {
				$mobFrontContext = MediaWikiServices::getInstance()->getService( 'MobileFrontend.Context' );
				if ( $mobFrontContext->shouldDisplayMobileView() ) {
					$responsive = true;
				}
			}
			$options['responsive'] = $responsive;
		}

		$options['templateDirectory'] = __DIR__ . '/templates';
		parent::__construct( $options );
	}

	/**
	 * Calls getLanguages with caching.
	 * @return array
	 */
	private function getLanguagesCached() : array {
		if ( $this->languages !== null ) {
			return $this->languages;
		}
		$this->languages = $this->getLanguages();
		return $this->languages;
	}

	/**
	 * This should be upstreamed to the Skin class in core once the logic is finalized.
	 * Returns false if an editor has explicitly disabled languages on the page via the property
	 * `noexternallanglinks`, if the page is a special page without any languages, or if an action
	 * other than view is being used.
	 * @return bool
	 */
	private function canHaveLanguages() : bool {
		$action = Action::getActionName( $this->getContext() );
		if ( $action !== 'view' ) {
			return false;
		}
		// Wikibase introduces a magic word
		// When upstreaming this should be Wikibase agnostic.
		// https://www.mediawiki.org/wiki/Wikibase/Installation/Advanced_configuration#noexternallanglinks
		// If the property is not set, continue safely through the other if statements.
		if ( $this->getOutput()->getProperty( 'noexternallanglinks' ) ) {
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
	 * @inheritDoc
	 */
	public function getTemplateData() : array {
		$contentNavigation = $this->buildContentNavigationUrls();
		$skin = $this;
		$out = $skin->getOutput();
		$title = $out->getTitle();
		$parentData = parent::getTemplateData();

		if ( $this->shouldHideLanguages() ) {
			$parentData['data-portlets']['data-languages'] = null;
		}

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
			'page-isarticle' => (bool)$out->isArticle(),

			// Remember that the string '0' is a valid title.
			// From OutputPage::getPageTitle, via ::setPageTitle().
			'html-title' => $out->getPageTitle(),

			'html-categories' => $skin->getCategories(),

			'input-location' => $this->getSearchBoxInputLocation(),

			'sidebar-visible' => $this->isSidebarVisible(),

			'is-language-in-header' => $this->isLanguagesInHeader(),

			'should-search-expand' => $this->shouldSearchExpand(),
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

		return $commonSkinData;
	}

	/**
	 * Gets the value of the "input-location" parameter for the SearchBox Mustache template.
	 *
	 * @return string Either `Constants::SEARCH_BOX_INPUT_LOCATION_DEFAULT` or
	 *  `Constants::SEARCH_BOX_INPUT_LOCATION_MOVED`
	 */
	private function getSearchBoxInputLocation() : string {
		if ( $this->isLegacy() ) {
			return Constants::SEARCH_BOX_INPUT_LOCATION_DEFAULT;
		}

		return Constants::SEARCH_BOX_INPUT_LOCATION_MOVED;
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
	private function shouldSearchExpand() : bool {
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
	 * Combines class and other HTML data required to create the button
	 * for the languages in header feature with the existing language portletData.
	 *
	 * @param array $portletData returned by SkinMustache
	 * @return array enhanced $portletData
	 */
	private function createULSLanguageButton( $portletData ) {
		$languageButtonData = [
			'id' => 'p-lang-btn',
			'label' => $this->msg(
				'vector-language-button-label',
				count( $this->getLanguagesCached() )
			)->parse(),
			'heading-class' =>
				' vector-menu-heading ' .
				' mw-ui-icon ' .
				' mw-ui-icon-before ' .
				' mw-ui-icon-wikimedia-language ' .
				' mw-ui-button mw-ui-quiet ' .
				// ext.uls.interface attaches click handler to this selector.
				' mw-interlanguage-selector ',
			];
		return array_merge( $portletData, $languageButtonData );
	}

	/**
	 * helper for applying Vector menu classes to portlets
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

		if ( $portletData['id'] === 'p-lang' && $this->isLanguagesInHeader() ) {
			$portletData = $this->createULSLanguageButton( $portletData );
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
	) : array {
		switch ( $label ) {
			case 'actions':
			case 'variants':
				$type = self::MENU_TYPE_DROPDOWN;
				break;
			case 'views':
			case 'namespaces':
				$type = self::MENU_TYPE_TABS;
				break;
			case 'personal':
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

		return $portletData + [
			'is-dropdown' => $type === self::MENU_TYPE_DROPDOWN,
		];
	}
}
