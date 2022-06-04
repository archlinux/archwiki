<?php
namespace Vector;

use FatalError;

/**
 * A namespace for Vector constants for internal Vector usage only. **Do not rely on this file as an
 * API as it may change without warning at any time.**
 * @package Vector
 * @internal
 */
final class Constants {
	/**
	 * This is tightly coupled to the ConfigRegistry field in skin.json.
	 * @var string
	 */
	public const SKIN_NAME_MODERN = 'vector-2022';

	/**
	 * This is tightly coupled to the ConfigRegistry field in skin.json.
	 * @var string
	 */
	public const SKIN_NAME_LEGACY = 'vector';

	// These are tightly coupled to PREF_KEY_SKIN_VERSION and skin.json's configs. See skin.json for
	// documentation.
	/**
	 * @var string
	 */
	public const SKIN_VERSION_LEGACY = '1';
	/**
	 * @var string
	 */
	public const SKIN_VERSION_LATEST = '2';

	/**
	 * @var string
	 */
	public const SERVICE_CONFIG = 'Vector.Config';

	/**
	 * @var string
	 */
	public const SERVICE_FEATURE_MANAGER = 'Vector.FeatureManager';

	// These are tightly coupled to skin.json's config.
	/**
	 * @var string
	 */
	public const CONFIG_KEY_SHOW_SKIN_PREFERENCES = 'VectorShowSkinPreferences';
	/**
	 * @var string
	 */
	public const CONFIG_KEY_DEFAULT_SKIN_VERSION = 'VectorDefaultSkinVersion';
	/**
	 * @var string
	 */
	public const CONFIG_KEY_DEFAULT_SKIN_VERSION_FOR_EXISTING_ACCOUNTS =
		'VectorDefaultSkinVersionForExistingAccounts';
	/**
	 * @var string
	 */
	public const CONFIG_KEY_DEFAULT_SKIN_VERSION_FOR_NEW_ACCOUNTS =
		'VectorDefaultSkinVersionForNewAccounts';

	/**
	 * @var string
	 */
	public const CONFIG_KEY_DEFAULT_SIDEBAR_VISIBLE_FOR_AUTHORISED_USER =
		'VectorDefaultSidebarVisibleForAuthorisedUser';

	/**
	 * @var string
	 */
	public const CONFIG_KEY_DEFAULT_SIDEBAR_VISIBLE_FOR_ANONYMOUS_USER =
		'VectorDefaultSidebarVisibleForAnonymousUser';

	/**
	 * @var string
	 */
	public const PREF_KEY_SKIN = 'skin';

	/**
	 * @var string
	 */
	public const PREF_KEY_SKIN_VERSION = 'VectorSkinVersion';

	/**
	 * @var string
	 */
	public const PREF_KEY_SIDEBAR_VISIBLE = 'VectorSidebarVisible';

	// These are used in the Feature Management System.
	/**
	 * Also known as `$wgFullyInitialised`. Set to true in core/includes/Setup.php.
	 * @var string
	 */
	public const CONFIG_KEY_FULLY_INITIALISED = 'FullyInitialised';

	/**
	 * @var string
	 */
	public const REQUIREMENT_FULLY_INITIALISED = 'FullyInitialised';

	/**
	 * @var string
	 */
	public const REQUIREMENT_LATEST_SKIN_VERSION = 'LatestSkinVersion';

	/**
	 * @var string
	 */
	public const FEATURE_LATEST_SKIN = 'LatestSkin';

	/**
	 * @var string
	 */
	public const FEATURE_LANGUAGE_IN_HEADER = 'LanguageInHeader';

	/**
	 * @var string
	 */
	public const CONFIG_KEY_DISABLE_SIDEBAR_PERSISTENCE = 'VectorDisableSidebarPersistence';

	/**
	 * @var string
	 */
	public const CONFIG_KEY_LANGUAGE_IN_HEADER = 'VectorLanguageInHeader';

	/**
	 * @var string
	 */
	public const REQUIREMENT_LANGUAGE_IN_HEADER = 'LanguageInHeader';

	/**
	 * Defines whether or not the Language in header A/B test is running. See
	 * https://phabricator.wikimedia.org/T280825 for additional detail about the test.
	 *
	 * Note well that if the associated config value is falsy, then we fall back to choosing the
	 * language treatment based on the `VectorLanguageInHeader` config variable.
	 *
	 * @var string
	 */
	public const CONFIG_LANGUAGE_IN_HEADER_TREATMENT_AB_TEST = 'VectorLanguageInHeaderTreatmentABTest';

	// These are used for query parameters.
	/**
	 * If undefined and AB test enabled, user will be bucketed as usual.
	 *
	 * If set, overrides the language in header AB test config:
	 *
	 * 'languageinheader=0' will show existing treatment.
	 * 'languageinheader=1' will show new treatment.
	 *
	 * @var string
	 */
	public const QUERY_PARAM_LANGUAGE_IN_HEADER = 'languageinheader';

	/**
	 * Override the skin version user preference and site Config. See readme.
	 * @var string
	 */
	public const QUERY_PARAM_SKIN_VERSION = 'useskinversion';

	/**
	 * Override the skin user preference and site Config. See readme.
	 * @var string
	 */
	public const QUERY_PARAM_SKIN = 'useskin';

	/**
	 * @var string
	 */
	public const QUERY_PARAM_STICKY_HEADER = 'vectorstickyheader';

	/**
	 * @var string
	 */
	public const QUERY_PARAM_STICKY_HEADER_EDIT = 'vectorstickyheaderedit';

	/**
	 * @var string
	 */
	public const CONFIG_STICKY_HEADER = 'VectorStickyHeader';

	/**
	 * @var string
	 */
	public const CONFIG_STICKY_HEADER_EDIT = 'VectorStickyHeaderEdit';

	/**
	 * @var string
	 */
	public const REQUIREMENT_STICKY_HEADER = 'StickyHeader';

	/**
	 * @var string
	 */
	public const REQUIREMENT_STICKY_HEADER_EDIT = 'StickyHeaderEdit';

	/**
	 * @var string
	 */
	public const FEATURE_STICKY_HEADER = 'StickyHeader';

	/**
	 * @var string
	 */
	public const FEATURE_STICKY_HEADER_EDIT = 'StickyHeaderEdit';

	/**
	 * Defines whether the Sticky Header A/B test is running. See
	 * https://phabricator.wikimedia.org/T292587 for additional detail about the test.
	 *
	 * @var string
	 */
	public const CONFIG_STICKY_HEADER_TREATMENT_AB_TEST_ENROLLMENT = 'VectorWebABTestEnrollment';

	/**
	 * The `mediawiki.searchSuggest` protocol piece of the SearchSatisfaction instrumention reads
	 * the value of an element with the "data-search-loc" attribute and set the event's
	 * `inputLocation` property accordingly.
	 *
	 * When the search widget is moved as part of the "Search 1: Search widget move" feature, the
	 * "data-search-loc" attribute is set to this value.
	 *
	 * See also:
	 * - https://www.mediawiki.org/wiki/Reading/Web/Desktop_Improvements/Features#Search_1:_Search_widget_move
	 * - https://phabricator.wikimedia.org/T261636 and https://phabricator.wikimedia.org/T256100
	 * - https://gerrit.wikimedia.org/g/mediawiki/core/+/61d36def2d7adc15c88929c824b444f434a0511a/resources/src/mediawiki.searchSuggest/searchSuggest.js#106
	 *
	 * @var string
	 */
	public const SEARCH_BOX_INPUT_LOCATION_MOVED = 'header-moved';

	/**
	 * Similar to `Constants::SEARCH_BOX_INPUT_LOCATION_MOVED`, when the search widget hasn't been
	 * moved, the "data-search-loc" attribute is set to this value.
	 *
	 * @var string
	 */
	public const SEARCH_BOX_INPUT_LOCATION_DEFAULT = 'header-navigation';

	/**
	 * @var string
	 */
	public const REQUIREMENT_IS_MAIN_PAGE = 'IsMainPage';

	/**
	 * @var string
	 */
	public const REQUIREMENT_LANGUAGE_IN_MAIN_PAGE_HEADER = 'LanguageInMainPageHeader';

	/**
	 * @var string
	 */
	public const CONFIG_LANGUAGE_IN_MAIN_PAGE_HEADER = 'VectorLanguageInMainPageHeader';

	/**
	 * @var string
	 */
	public const QUERY_PARAM_LANGUAGE_IN_MAIN_PAGE_HEADER = 'languageinmainpageheader';

	/**
	 * @var string
	 */
	public const FEATURE_LANGUAGE_IN_MAIN_PAGE_HEADER = 'LanguageInMainPageHeader';

	/**
	 * @var string
	 */
	public const REQUIREMENT_LANGUAGE_ALERT_IN_SIDEBAR = 'LanguageAlertInSidebar';

	/**
	 * @var string
	 */
	public const CONFIG_LANGUAGE_ALERT_IN_SIDEBAR = 'VectorLanguageAlertInSidebar';

	/**
	 * @var string
	 */
	public const QUERY_PARAM_LANGUAGE_ALERT_IN_SIDEBAR = 'languagealertinsidebar';

	/**
	 * @var string
	 */
	public const FEATURE_LANGUAGE_ALERT_IN_SIDEBAR = 'LanguageAlertInSidebar';

	/**
	 * @var string
	 */
	public const REQUIREMENT_TABLE_OF_CONTENTS = 'TableOfContents';

	/**
	 * @var string
	 */
	public const CONFIG_TABLE_OF_CONTENTS = 'VectorTableOfContents';

	/**
	 * @var string
	 */
	public const QUERY_PARAM_TABLE_OF_CONTENTS = 'tableofcontents';

	/**
	 * @var string
	 */
	public const FEATURE_TABLE_OF_CONTENTS = 'TableOfContents';

	/**
	 * This class is for namespacing constants only. Forbid construction.
	 * @throws FatalError
	 * @return never
	 */
	private function __construct() {
		throw new FatalError( "Cannot construct a utility class." );
	}
}
