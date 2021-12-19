/** @module search */
var
	Vue = require( 'vue' ).default || require( 'vue' ),
	App = require( './App.vue' ),
	config = require( './config.json' );

/**
 * @param {HTMLElement} searchForm
 * @param {HTMLInputElement} search
 * @param {string|null} searchPageTitle title of page used for searching e.g. Special:Search
 *  If null then this will default to Special:Search.
 * @return {void}
 */
function initApp( searchForm, search, searchPageTitle ) {
	// eslint-disable-next-line no-new
	new Vue( {
		el: searchForm,
		/**
		 *
		 * @param {Function} createElement
		 * @return {Vue.VNode}
		 */
		render: function ( createElement ) {
			return createElement( App, {
				props: $.extend( {
					autofocusInput: search === document.activeElement,
					action: searchForm.getAttribute( 'action' ),
					searchAccessKey: search.getAttribute( 'accessKey' ),
					searchPageTitle: searchPageTitle,
					searchTitle: search.getAttribute( 'title' ),
					searchPlaceholder: search.getAttribute( 'placeholder' ),
					searchQuery: search.value
				},
				// Pass additional config from server.
				config
				)
			} );
		}
	} );
}
/**
 * @param {Document} document
 * @return {void}
 */
function main( document ) {
	var
		searchForm = /** @type {HTMLElement} */ ( document.querySelector( '#searchform' ) ),
		titleInput = /** @type {HTMLInputElement|null} */ (
			searchForm.querySelector( 'input[name=title]' )
		),
		search = /** @type {HTMLInputElement|null} */ ( document.getElementById( 'searchInput' ) );
	if ( search && searchForm ) {
		initApp( searchForm, search, titleInput && titleInput.value );
	}
}
main( document );
