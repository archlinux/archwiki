/**
 * Disabling this rule as it's only necessary for
 * combining multiple class names and documenting the output.
 * That doesn't happen in this file but the linter still throws an error.
 * https://github.com/wikimedia/eslint-plugin-mediawiki/blob/master/docs/rules/class-doc.md
 */
/* eslint-disable mediawiki/class-doc */

/** @interface VectorResourceLoaderVirtualConfig */
/** @interface MediaWikiPageReadyModule */

var /** @type {VectorResourceLoaderVirtualConfig} */
	config = require( /** @type {string} */ ( './config.json' ) ),
	// T251544: Collect search performance metrics to compare Vue search with
	// mediawiki.searchSuggest performance.
	CAN_TEST_SEARCH = !!(
		window.performance &&
		performance.mark &&
		performance.measure &&
		performance.getEntriesByName ),
	LOAD_START_MARK = 'mwVectorVueSearchLoadStart',
	LOAD_END_MARK = 'mwVectorVueSearchLoadEnd',
	LOAD_MEASURE = 'mwVectorVueSearchLoadStartToLoadEnd',
	SEARCH_FORM_ID = 'simpleSearch',
	SEARCH_INPUT_ID = 'searchInput',
	SEARCH_LOADING_CLASS = 'search-form__loader';

/**
 * Loads the search module via `mw.loader.using` on the element's
 * focus event. Or, if the element is already focused, loads the
 * search module immediately.
 * After the search module is loaded, executes a function to remove
 * the loading indicator.
 *
 * @param {HTMLElement} element search input.
 * @param {string} moduleName resourceLoader module to load.
 * @param {function(): void} afterLoadFn function to execute after search module loads.
 */
function loadSearchModule( element, moduleName, afterLoadFn ) {
	var SHOULD_TEST_SEARCH = CAN_TEST_SEARCH && moduleName === 'skins.vector.search';

	function requestSearchModule() {
		if ( SHOULD_TEST_SEARCH ) {
			performance.mark( LOAD_START_MARK );
		}
		mw.loader.using( moduleName, afterLoadFn );
		element.removeEventListener( 'focus', requestSearchModule );
	}

	if ( document.activeElement === element ) {
		requestSearchModule();
	} else {
		element.addEventListener( 'focus', requestSearchModule );
	}
}

/**
 * Event callback that shows or hides the loading indicator based on the event type.
 * The loading indicator states are:
 * 1. Show on input event (while user is typing)
 * 2. Hide on focusout event (when user removes focus from the input )
 * 3. Show when input is focused, if it contains a query. (in case user re-focuses on input)
 *
 * @param {Event} event
 */
function renderSearchLoadingIndicator( event ) {

	var form = /** @type {HTMLElement} */ ( event.currentTarget ),
		input = /** @type {HTMLInputElement} */ ( event.target );

	if (
		!( event.currentTarget instanceof HTMLElement ) ||
		!( event.target instanceof HTMLInputElement ) ||
		!( input.id === SEARCH_INPUT_ID ) ) {
		return;
	}

	if ( !form.dataset.loadingMsg ) {
		form.dataset.loadingMsg = mw.msg( 'vector-search-loader' );
	}

	if ( event.type === 'input' ) {
		form.classList.add( SEARCH_LOADING_CLASS );

	} else if ( event.type === 'focusout' ) {
		form.classList.remove( SEARCH_LOADING_CLASS );

	} else if ( event.type === 'focusin' && input.value.trim() ) {
		form.classList.add( SEARCH_LOADING_CLASS );
	}
}

/**
 * Attaches or detaches the event listeners responsible for activating
 * the loading indicator.
 *
 * @param {HTMLElement} element
 * @param {boolean} attach
 * @param {function(Event): void} eventCallback
 */
function setLoadingIndicatorListeners( element, attach, eventCallback ) {

	/** @type { "addEventListener" | "removeEventListener" } */
	var addOrRemoveListener = ( attach ? 'addEventListener' : 'removeEventListener' );

	[ 'input', 'focusin', 'focusout' ].forEach( function ( eventType ) {
		element[ addOrRemoveListener ]( eventType, eventCallback );
	} );

	if ( !attach ) {
		element.classList.remove( SEARCH_LOADING_CLASS );
	}
}

/**
 * Marks when the lazy load has completed.
 */
function markLoadEnd() {
	if ( performance.getEntriesByName( LOAD_START_MARK ).length ) {
		performance.mark( LOAD_END_MARK );
		performance.measure( LOAD_MEASURE, LOAD_START_MARK, LOAD_END_MARK );
	}
}

/**
 * Initialize the loading of the search module as well as the loading indicator.
 * Only initialize the loading indicator when not using the core search module.
 *
 * @param {Document} document
 */
function initSearchLoader( document ) {
	var searchForm = document.getElementById( SEARCH_FORM_ID ),
		searchInput = document.getElementById( SEARCH_INPUT_ID ),
		shouldUseCoreSearch;

	// Allow developers to defined $wgVectorSearchHost in LocalSettings to target different APIs
	if ( config.wgVectorSearchHost ) {
		mw.config.set( 'wgVectorSearchHost', config.wgVectorSearchHost );
	}

	if ( !searchForm || !searchInput ) {
		return;
	}

	shouldUseCoreSearch = !document.body.classList.contains( 'skin-vector-search-vue' );

	/**
	 * 1. If $wgVectorUseWvuiSearch is false,
	 *    or we are in a browser that doesn't support fetch
	 *    load the legacy searchSuggest module. The check for window.fetch
	 *    can be removed when IE11 support is finally officially dropped.
	 * 2. If we're using a different search module, enable the loading indicator
	 *    before the search module loads.
	 **/
	if ( shouldUseCoreSearch || !window.fetch ) {
		loadSearchModule( searchInput, 'mediawiki.searchSuggest', function () {} );
	} else {
		// Remove tooltips while Vue search is still loading
		searchInput.setAttribute( 'autocomplete', 'off' );
		searchInput.removeAttribute( 'title' );
		setLoadingIndicatorListeners( searchForm, true, renderSearchLoadingIndicator );
		loadSearchModule(
			searchInput,
			'skins.vector.search',
			function () {
				markLoadEnd();

				setLoadingIndicatorListeners(
					/** @type {HTMLElement} */ ( searchForm ),
					false,
					renderSearchLoadingIndicator
				);
			}
		);

	}
}

module.exports = {
	initSearchLoader: initSearchLoader
};
