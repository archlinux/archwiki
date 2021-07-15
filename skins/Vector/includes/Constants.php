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
	public const SKIN_NAME = 'vector';

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

	// These are used for query parameters.
	/**
	 * Override the skin version user preference and site Config. See readme.
	 * @var string
	 */
	public const QUERY_PARAM_SKIN_VERSION = 'useskinversion';

	/**
	 * @var string
	 */
	public const FEATURE_USE_WVUI_SEARCH = 'UseWvuiSearch';

	/**
	 * @var string
	 */
	public const CONFIG_KEY_USE_WVUI_SEARCH = 'VectorUseWvuiSearch';

	/**
	 * @var string
	 */
	public const REQUIREMENT_USE_WVUI_SEARCH = 'VectorUseWvuiSearch';

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
	 * Defines whether or not the Core/Vue.js Search Widget A/B test is running. See
	 * https://phabricator.wikimedia.org/T261647 for additional detail about the test.
	 *
	 * Note well that if the associated config value is falsy, then we fall back to choosing the
	 * search widget treatment based on the `VectorUseWvuiSearch` config variable (see
	 * `resources/skins.vector.js/searchLoader.js`).
	 *
	 * @var string
	 */
	public const CONFIG_SEARCH_TREATMENT_AB_TEST = 'VectorSearchTreatmentABTest';

	/**
	 * This class is for namespacing constants only. Forbid construction.
	 * @throws FatalError
	 */
	private function __construct() {
		throw new FatalError( "Cannot construct a utility class." );
	}
}
