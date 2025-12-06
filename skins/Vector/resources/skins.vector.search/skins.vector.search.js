/** @module search */

const
	Vue = require( 'vue' ),
	{
		App,
		restSearchClient,
		urlGenerator
	} = require( /** @type {string} */ ( 'mediawiki.skinning.typeaheadSearch' ) );

const searchConfig = require( './searchConfig.json' );
const inNamespace = searchConfig.ContentNamespaces.includes( mw.config.get( 'wgNamespaceNumber' ) );
// apiUrl defaults to /rest.php if not set
const searchApiUrl = searchConfig.VectorTypeahead.apiUrl || mw.config.get( 'wgScriptPath' ) + '/rest.php';
const recommendationApiUrl = inNamespace ? searchConfig.VectorTypeahead.recommendationApiUrl : '';
const searchOptions = searchConfig.VectorTypeahead.options;
// The param config must be defined for empty search recommendations to be enabled.
const showEmptySearchRecommendations = inNamespace && recommendationApiUrl;

/**
 * @param {Element} searchBox
 * @param {Object} [restClient]
 * @param {Object} [urlGeneratorInstance]
 * @return {void}
 */
function initApp( searchBox, restClient, urlGeneratorInstance ) {
	// The config variables enable customization of the URL generator and search client
	// by Wikidata. Note: These must be defined by Wikidata in the page HTML and are not
	// read from LocalSettings.php
	const urlGeneratorConfig = mw.config.get(
		'wgVectorSearchUrlGenerator'
	);
	const searchClientConfig = mw.config.get(
		'wgVectorSearchClient'
	);
	if ( urlGeneratorConfig ) {
		mw.log.warn( `Use of mw.config.get( "wgVectorSearchUrlGenerator") is deprecated.
Use SkinPageReadyConfig hook to replace the search module (T395641).` );
	}
	if ( searchClientConfig ) {
		mw.log.warn( `Use of mw.config.get( "wgVectorSearchClient") is deprecated.
Use SkinPageReadyConfig hook to replace the search module (T395641).` );
	}
	urlGeneratorInstance = urlGeneratorInstance || urlGeneratorConfig ||
		urlGenerator( mw.config.get( 'wgScript' ) );
	restClient = restClient || searchClientConfig ||
		restSearchClient(
			searchApiUrl,
			urlGeneratorInstance,
			recommendationApiUrl
		);
	const searchForm = searchBox.querySelector( '.cdx-search-input' ),
		titleInput = /** @type {HTMLInputElement|null} */ (
			searchBox.querySelector( 'input[name=title]' )
		),
		search = /** @type {HTMLInputElement|null} */ ( searchBox.querySelector( 'input[name=search]' ) ),
		searchPageTitle = titleInput && titleInput.value,
		searchContainer = searchBox.querySelector( '.vector-typeahead-search-container' );

	if ( !searchForm || !search || !titleInput ) {
		throw new Error( 'Attempted to create Vue search element from an incompatible element.' );
	}

	// @ts-ignore MediaWiki-specific function
	Vue.createMwApp(
		App, Object.assign( {
			prefixClass: 'vector-',
			id: searchForm.id,
			autocapitalizeValue: search.getAttribute( 'autocapitalize' ),
			autofocusInput: search === document.activeElement,
			action: searchForm.getAttribute( 'action' ),
			searchAccessKey: search.getAttribute( 'accessKey' ),
			searchPageTitle,
			restClient,
			urlGenerator: urlGeneratorInstance,
			searchTitle: search.getAttribute( 'title' ),
			searchPlaceholder: search.getAttribute( 'placeholder' ),
			searchQuery: search.value,
			autoExpandWidth: searchBox ? searchBox.classList.contains( 'vector-search-box-auto-expand-width' ) : false,
			showEmptySearchRecommendations
		// Pass additional config from server.
		}, searchOptions )
	)
		.mount( searchContainer );
}
/**
 * @param {Document} document
 * @param {Object} [restClient]
 * @param {Object} [urlGeneratorInstance]
 * @return {void}
 */
function main( document, restClient, urlGeneratorInstance ) {
	document.querySelectorAll( '.vector-search-box' )
		.forEach( ( node ) => {
			initApp( node, restClient, urlGeneratorInstance );
		} );
}

/**
 * @ignore
 * @param {Object} [restClient] used by Wikidata to configure the search API
 * @param {Object} [urlGeneratorInstance] used by Wikidata to configure the search API
 */
function init( restClient, urlGeneratorInstance ) {
	main( document, restClient, urlGeneratorInstance );
}

module.exports = {
	init
};
