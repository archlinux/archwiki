const TemplateMenuItem = require( './TemplateMenuItem.js' );
const templateDiscoveryConfig = require( './config.json' );
const FavoritesStore = require( './FavoritesStore.js' );
const mwConfig = require( './mwConfig.json' );

/**
 * @class
 * @extends OO.ui.ComboBoxInputWidget
 *
 * @constructor
 * @param {Object} [config] Configuration options.
 * @param {number} [config.limit=10]
 * @param {mw.Api} [config.api] Optional MediaWiki API, for testing
 * @param {FavoritesStore} config.favoritesStore Data store
 */
function SearchWidget( config ) {
	config = Object.assign( {
		placeholder: OO.ui.deferMsg( 'templatedata-search-placeholder' ),
		icon: 'search'
	}, config );
	SearchWidget.super.call( this, config );
	OO.ui.mixin.LookupElement.call( this );

	this.limit = config.limit || 10;
	this.api = config.api || new mw.Api();
	this.favoritesStore = config.favoritesStore;
}

/* Setup */

OO.inheritClass( SearchWidget, OO.ui.ComboBoxInputWidget );
OO.mixinClass( SearchWidget, OO.ui.mixin.LookupElement );

/* Events */

/**
 * When a template is choosen from the menu.
 *
 * @event choose
 * @param {Object} The template data of the chosen template.
 */

/**
 * When the current value of the search input matches a search result (regardless of whether that
 * result is highlighted).
 *
 * @event match
 * @param {Object} templateData Template data of the matched search result.
 */

/* Methods */

/**
 * This helper method is modeled after mw.widgets.TitleWidget, even if this is *not* a TitleWidget.
 *
 * @private
 * @method
 * @param {string} query What the user typed
 * @return {Object} Parameters for the MediaWiki action API
 */
SearchWidget.prototype.getApiParams = function ( query ) {
	const params = {
		action: 'templatedata',
		includeMissingTitles: 1,
		lang: mw.config.get( 'wgUserLanguage' ),
		generator: 'prefixsearch',
		gpssearch: query,
		gpsnamespace: mw.config.get( 'wgNamespaceIds' ).template,
		gpslimit: this.limit,
		redirects: 1
	};

	if ( templateDiscoveryConfig.cirrusSearchLoaded ) {
		Object.assign( params, {
			generator: 'search',
			gsrsearch: params.gpssearch,
			gsrnamespace: params.gpsnamespace,
			gsrlimit: params.gpslimit,
			gsrprop: [ 'redirecttitle' ]
		} );
		// Adding the asterisk to emulate a prefix search behavior. It does not make sense in all
		// cases though. We're limiting it to be add only of the term ends with a letter or numeric
		// character.
		// eslint-disable-next-line es-x/no-regexp-unicode-property-escapes, prefer-regex-literals
		const endsWithAlpha = new RegExp( '[\\p{L}\\p{N}]$', 'u' );
		if ( endsWithAlpha.test( params.gsrsearch ) ) {
			params.gsrsearch += '*';
		}

		delete params.gpssearch;
		delete params.gpsnamespace;
		delete params.gpslimit;
	}

	return params;
};

/**
 * Get a new request object of the current lookup query value.
 *
 * @protected
 * @method
 * @return {jQuery.Promise} jQuery AJAX object, or promise object with an .abort() method
 */
SearchWidget.prototype.getLookupRequest = function () {
	const query = this.getValue(),
		params = this.getApiParams( query );
	let promise = this.api.get( params );

	// No point in running prefix search a second time
	if ( params.generator !== 'prefixsearch' ) {
		promise = promise
			.then( this.addExactMatch.bind( this ) )
			.promise( { abort: function () {} } );
	}

	return promise;
};

/**
 * @private
 * @method
 * @param {Object} response Action API response from server
 * @return {Object} Modified response
 */
SearchWidget.prototype.addExactMatch = function ( response ) {
	const query = this.getValue(),
		lowerQuery = query.trim().toLowerCase();
	if ( !response.pages || !lowerQuery ) {
		return response;
	}

	const containsExactMatch = Object.keys( response.pages ).some( ( pageId ) => {
		const page = response.pages[ pageId ],
			title = mw.Title.newFromText( page.title );
		return title.getMainText().toLowerCase() === lowerQuery;
	} );
	if ( containsExactMatch ) {
		return response;
	}

	const limit = this.limit;
	return this.api.get( {
		action: 'templatedata',
		includeMissingTitles: 1,
		lang: mw.config.get( 'wgUserLanguage' ),
		// Can't use a direct lookup by title because we need this to be case-insensitive
		generator: 'prefixsearch',
		gpssearch: query,
		gpsnamespace: mw.config.get( 'wgNamespaceIds' ).template,
		// Try to fill with prefix matches, otherwise just the top-1 prefix match
		gpslimit: limit
	} ).then( ( prefixMatches ) => {
		// action=templatedata returns page objects in `{ pages: {} }`, keyed by page id
		// Copy keys because the loop below needs an ordered array, not an object
		for ( const pageId in prefixMatches.pages ) {
			prefixMatches.pages[ pageId ].pageid = pageId;
		}
		// Make sure the loop below processes the results by relevance
		const pages = OO.getObjectValues( prefixMatches.pages )
			.sort( ( a, b ) => a.index - b.index );
		for ( const i in pages ) {
			const prefixMatch = pages[ i ];
			if ( !( prefixMatch.pageid in response.pages ) ) {
				// Move prefix matches to the top, indexed from -9 to 0, relevant for e.g. {{!!}}
				// Note: Sorting happens down in getLookupCacheDataFromResponse()
				prefixMatch.index -= limit;
				response.pages[ prefixMatch.pageid ] = prefixMatch;
			}
			// Check only after the top-1 prefix match is guaranteed to be present
			// Note: Might be 11 at this point, truncated in getLookupCacheDataFromResponse()
			if ( Object.keys( response.pages ).length >= limit ) {
				break;
			}
		}
		return response;
	},
	// Proceed with the unmodified response in case the additional API request failed
	() => response
	)
		.promise( { abort: function () {} } );
};

/**
 * Pre-process data returned by the request from {@see getLookupRequest}.
 *
 * The return value of this function will be cached, and any further queries for the given value
 * will use the cache rather than doing API requests.
 *
 * @protected
 * @method
 * @param {Object} response Response from server
 * @return {Object[]} Config for {@see TemplateMenuItem} widgets
 */
SearchWidget.prototype.getLookupCacheDataFromResponse = function ( response ) {
	const templateData = response.pages;

	// Prepare the separate "redirects" structure to be converted to the CirrusSearch
	// "redirecttitle" field
	const redirectedFrom = {};
	if ( response.redirects ) {
		response.redirects.forEach( ( redirect ) => {
			redirectedFrom[ redirect.to ] = redirect.from;
		} );
	}

	const searchResults = Object.keys( templateData ).map( ( pageId ) => {
		const page = templateData[ pageId ];
		page.pageId = pageId;

		if ( !page.redirecttitle && page.title in redirectedFrom ) {
			page.redirecttitle = redirectedFrom[ page.title ];
		}

		const title = mw.Title.newFromText( page.title );

		// Skip non-TemplateDataEditorNamespaces namespaces
		if ( !mwConfig.TemplateDataEditorNamespaces.includes( title.getNamespaceId() ) ) {
			return null;
		}

		/**
		 * Config for the {@see TemplateMenuItem} widget:
		 * - data: {@see OO.ui.Element} and getData()
		 * - label: {@see OO.ui.mixin.LabelElement} and getLabel()
		 * - description: {@see TemplateMenuItem}
		 */
		return {
			data: page,
			label: title.getRelativeText( mw.config.get( 'wgNamespaceIds' ).template ),
			description: page.description
		};
	// Filter map results to remove null values
	} ).filter( ( result ) => result !== null );

	const lowerQuery = this.getValue().trim().toLowerCase();
	searchResults.sort( ( a, b ) => {
		// Force exact matches to be at the top
		if ( a.label.toLowerCase() === lowerQuery ) {
			return -1;
		} else if ( b.label.toLowerCase() === lowerQuery ) {
			return 1;
		}

		// Restore original (prefix)search order, possibly messed up because of the generator
		if ( 'index' in a.data && 'index' in b.data ) {
			return a.data.index - b.data.index;
		}

		return 0;
	} );

	// Might be to many results because of the additional exact match search above
	if ( searchResults.length > this.limit ) {
		searchResults.splice( this.limit );
	}

	return searchResults;
};

/**
 * Get a list of menu option widgets from the (possibly cached) data returned by
 * {@see getLookupCacheDataFromResponse}.
 *
 * @protected
 * @method
 * @param {Object[]} data Search results from {@see getLookupCacheDataFromResponse}
 * @return {OO.ui.MenuOptionWidget[]}
 */
SearchWidget.prototype.getLookupMenuOptionsFromData = function ( data ) {
	return data.map( ( config ) => {
		// See if this template matches, and if it does then emit an event.
		const valueAsTitle = new mw.Title( this.getValue() );
		if ( valueAsTitle.getMainText() === config.label ) {
			this.emit( 'match', config.data );
		}
		return new TemplateMenuItem( config, this.favoritesStore );
	} );
};

/**
 * Handle menu item 'choose' event, updating the text input value to the value of the clicked item.
 *
 * @protected
 * @fires choose
 * @param {OO.ui.MenuOptionWidget} item Selected item
 */
SearchWidget.prototype.onLookupMenuChoose = function ( item ) {
	this.setValue( item.getLabel() );
	this.emit( 'choose', item.getData() );
};

module.exports = SearchWidget;
