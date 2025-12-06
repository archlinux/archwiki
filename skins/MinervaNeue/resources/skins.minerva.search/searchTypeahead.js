const Vue = require( 'vue' );
const router = require( 'mediawiki.router' );
const {
	App, restSearchClient, urlGenerator
} = require( 'mediawiki.skinning.typeaheadSearch' );
const searchConfig = require( './searchConfig.json' ).MinervaTypeahead;
const recommendationApiUrl = searchConfig.recommendationApiUrl;
const searchApiUrl = searchConfig.apiUrl || `${ mw.config.get( 'wgScriptPath' ) }/rest.php`;
const searchOptions = searchConfig.options || {};
let appDefaults;

/**
 * @ignore
 * @param {Object} restClient
 * @param {Object} urlGeneratorInstance
 * @return {Object}
 */
function getSearchProps( restClient, urlGeneratorInstance ) {
	if ( appDefaults ) {
		return appDefaults;
	}
	const searchInput = document.getElementById( 'searchInput' );
	const searchForm = document.querySelector( '.minerva-header .minerva-search-form' );
	if ( !searchInput || !searchForm ) {
		throw new Error( 'Minerva missing .minerva-search-form or #searchInput' );
	}
	const action = searchForm.getAttribute( 'action' );
	const autocapitalizeValue = searchInput.getAttribute( 'autocapitalize' );
	const searchAccessKey = searchInput.getAttribute( 'accessKey' );
	const searchTitle = searchInput.getAttribute( 'title' );
	const searchPlaceholder = searchInput.getAttribute( 'placeholder' );
	const titleInput = document.querySelector( '.minerva-header input[name=title]' );
	const searchQuery = searchInput.value;
	const searchPageTitle = titleInput && titleInput.value;
	const searchTerm = searchInput.value;
	appDefaults = {
		router,
		restClient,
		supportsMobileExperience: true,
		urlGenerator: urlGeneratorInstance,
		id: 'minerva-overlay-search',
		autofocusInput: true,
		searchButtonLabel: '',
		autoExpandWidth: true,
		showEmptySearchRecommendations: !!recommendationApiUrl,
		action,
		searchQuery,
		searchTitle,
		searchTerm,
		searchPlaceholder,
		searchPageTitle,
		autocapitalizeValue,
		searchAccessKey
	};
	return Object.assign( appDefaults, searchOptions );
}

/**
 * @ignore
 * @param {Object} restClient
 * @param {Object} urlGeneratorInstance
 * @return {Vue.Component}
 */
function renderTypeaheadSearch( restClient, urlGeneratorInstance ) {
	return Vue.createMwApp(
		App,
		getSearchProps( restClient, urlGeneratorInstance )
	);
}

let searchTypeaheadInitialized = false;

/**
 * @ignore
 * @param {Object} [restClient]
 * @param {Object} [urlGeneratorInstance]
 * @return {Promise}
 */
function searchTypeahead( restClient, urlGeneratorInstance ) {
	// On first run we setup Vue app defaults, load and render the App.
	// Since all these elements will get destroyed they are cached
	// to appDefaults for future runs.
	if ( !urlGeneratorInstance ) {
		urlGeneratorInstance = urlGenerator( mw.config.get( 'wgScript' ) );
	}
	if ( !searchTypeaheadInitialized ) {
		const app = renderTypeaheadSearch(
			restClient || restSearchClient(
				searchApiUrl, urlGeneratorInstance, recommendationApiUrl
			),
			urlGeneratorInstance
		);
		app.mount( document.querySelector( 'header .minerva-search-form .search-box' ) );
		searchTypeaheadInitialized = true;
	}
}

module.exports = {
	searchTypeahead
};
