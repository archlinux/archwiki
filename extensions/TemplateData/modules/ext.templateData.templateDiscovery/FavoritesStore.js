const templateDiscoveryConfig = require( './config.json' );
const mwConfig = require( './mwConfig.json' );
const USER_PREFERENCE_NAME = 'templatedata-favorite-templates';

/**
 * @class
 * @mixes OO.EventEmitter
 *
 * @constructor
 */
function FavoritesStore() {
	// Mixin constructors
	OO.EventEmitter.call( this );

	this.favoritesArray = JSON.parse( mw.user.options.get( USER_PREFERENCE_NAME ) );
	this.maxFavorites = templateDiscoveryConfig.maxFavorites;
}

/* Setup */
OO.mixinClass( FavoritesStore, OO.EventEmitter );

/* Events */
/**
 * When favorite is removed
 *
 * @event removed
 * @param {number} pageId
 */
/**
 * When favorite is added
 *
 * @event added
 * @param {number} pageId
 */

/**
 * @return {Promise}
 */
FavoritesStore.prototype.getAllFavoritesDetails = function () {
	return this.getFavoriteDetail( this.favoritesArray.join( '|' ) );
};

/**
 * Get the details of a favorite (or favorites) by page ID(s)
 * pageId can be a number or a string of numbers separated by '|'.
 *
 * @param {number|string} pageId
 * @return {Promise}
 */
FavoritesStore.prototype.getFavoriteDetail = function ( pageId ) {
	return new mw.Api().get( {
		action: 'templatedata',
		includeMissingTitles: 1,
		pageids: pageId,
		lang: mw.config.get( 'wgUserLanguage' ),
		redirects: 1,
		formatversion: 2
	} ).then( ( data ) => {
		const redirectedFrom = {};
		if ( data.redirects ) {
			data.redirects.forEach( ( redirect ) => {
				redirectedFrom[ redirect.to ] = redirect.from;
			} );
		}
		const favorites = [];
		Object.keys( data.pages ).forEach( ( k ) => {
			const favorite = data.pages[ k ];
			// Skip if the page is missing, or in an invalid namespace
			if ( favorite.missing ||
				!mwConfig.TemplateDataEditorNamespaces.includes( favorite.ns )
			) {
				return;
			}
			favorite.pageId = k;
			if ( favorite.title in redirectedFrom ) {
				favorite.redirecttitle = redirectedFrom[ favorite.title ];
			}
			favorites.push( favorite );
		} );
		return favorites
			.sort( ( p1, p2 ) => p1.title === p2.title ? 0 : ( p1.title < p2.title ? -1 : 1 ) );
	} );
};

/**
 * Save the favorites array to the user options
 *
 * @param {Array} favoritesArray
 * @return {Promise}
 */
function save( favoritesArray ) {
	const json = JSON.stringify( favoritesArray );
	return new mw.Api().saveOption( USER_PREFERENCE_NAME, json, { errorsuselocal: 1, errorformat: 'html' } )
		.then( () => {
			this.favoritesArray = favoritesArray;
			mw.user.options.set( USER_PREFERENCE_NAME, json );
		},
		( code, response ) => {
			// The 'notloggedin' error is a special case in mw.Api.saveOptions()
			if ( code === 'notloggedin' ) {
				mw.notify( mw.msg( 'notloggedin' ), {
					type: 'error',
					title: mw.msg( 'templatedata-favorite-error' )
				} );
			} else {
				for ( const error of response.errors ) {
					mw.notify( error.html, {
						type: 'error',
						title: mw.msg( 'templatedata-favorite-error' )
					} );
				}
			}
			throw code;
		} );
}

/**
 * Parse a page ID to a number, or throw an error
 *
 * @param {string} pageId
 * @return {number} The parsed page ID
 * @throws {Error} If the pageId is not a number
 */
function parsePageId( pageId ) {
	const parsedPageId = parseInt( pageId );
	if ( isNaN( parsedPageId ) ) {
		throw new Error( 'Invalid pageId: ' + pageId );
	}
	return parsedPageId;
}

/**
 * Add a page ID to the favorites array
 *
 * @param {string} pageId
 * @return {Promise} Resolves when the page ID is added (or is not able to be).
 */
FavoritesStore.prototype.addFavorite = function ( pageId ) {
	if ( this.favoritesArray.length < this.maxFavorites ) {
		const newFavorites = this.favoritesArray;
		newFavorites.push( parsePageId( pageId ) );
		return save( newFavorites ).then( () => {
			this.emit( 'added', pageId );
			mw.notify(
				mw.msg( 'templatedata-favorite-added' ),
				{
					type: 'success',
					tag: 'templatedata-favorite-added'
				}
			);
		} );
	} else {
		mw.notify(
			mw.msg( 'templatedata-favorite-maximum-reached', this.maxFavorites ),
			{
				type: 'error',
				tag: 'templatedata-favorite-maximum-reached'
			}
		);
		return Promise.reject();
	}
};

/**
 * Remove a page ID from the favorites array
 *
 * @param {string} pageId
 * @return {Promise} Resolves when the page ID is removed (or is not able to be).
 */
FavoritesStore.prototype.removeFavorite = function ( pageId ) {
	const index = this.favoritesArray.indexOf( parsePageId( pageId ) );
	if ( index === -1 ) {
		return Promise.resolve();
	}
	const newFavorites = this.favoritesArray;
	newFavorites.splice( index, 1 );
	return save( newFavorites ).then( () => {
		this.emit( 'removed', pageId );
		mw.notify(
			mw.msg( 'templatedata-favorite-removed' ),
			{
				type: 'success',
				tag: 'templatedata-favorite-removed'
			}
		);
		return;
	} );
};

/**
 * Check if a page ID is in the favorites array
 *
 * @param {string} pageId
 * @return {boolean} Whether the page ID is in the favorites array
 */
FavoritesStore.prototype.isFavorite = function ( pageId ) {
	return this.favoritesArray.includes( parsePageId( pageId ) );
};

/**
 * Utility function to get the title of a page ID
 *
 * @param {number} pageId
 * @return {jQuery.Promise}
 */
FavoritesStore.prototype.getFavoriteTitle = function ( pageId ) {
	// TODO: Should this be cached in some way?
	return new mw.Api().get( {
		action: 'query',
		prop: 'info',
		pageids: pageId,
		formatversion: 2
	} );
};

module.exports = FavoritesStore;
