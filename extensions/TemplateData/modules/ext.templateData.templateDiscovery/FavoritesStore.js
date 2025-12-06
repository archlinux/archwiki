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
	return this.getFavoritesDetails( this.favoritesArray );
};

/**
 * Get the details of some favorites by page ID.
 *
 * @param {Array<number|string>} pageIds
 * @return {Promise}
 */
FavoritesStore.prototype.getFavoritesDetails = function ( pageIds ) {
	return new mw.Api().get( {
		action: 'templatedata',
		includeMissingTitles: 1,
		pageids: pageIds.join( '|' ),
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
				this.favoritesArray.splice( this.favoritesArray.indexOf( parseInt( k ) ), 1 );
				return;
			}
			favorite.pageId = k;
			if ( favorite.title in redirectedFrom ) {
				favorite.redirecttitle = redirectedFrom[ favorite.title ];
			}
			favorites.push( favorite );
		} );
		// Return favorites sorted by page id in this.favoritesArray
		return favorites.sort( ( p1, p2 ) => {
			const index1 = this.favoritesArray.indexOf( parseInt( p1.pageId ) );
			const index2 = this.favoritesArray.indexOf( parseInt( p2.pageId ) );
			return index1 === index2 ? 0 : ( index1 < index2 ? -1 : 1 );
		} );
	} );
};

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
		const newFavorites = [ ...this.favoritesArray ];
		newFavorites.push( parsePageId( pageId ) );
		return this.saveFavoritesArray( newFavorites ).then( () => {
			this.emit( 'added', pageId );
			mw.notify(
				mw.msg( 'templatedata-favorite-added' ),
				{
					type: 'info',
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
	const newFavorites = [ ...this.favoritesArray ];
	newFavorites.splice( index, 1 );
	return this.saveFavoritesArray( newFavorites ).then( () => {
		this.emit( 'removed', pageId );
		mw.notify(
			mw.msg( 'templatedata-favorite-removed' ),
			{
				type: 'info',
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
 * Save the favorites array to the user options
 *
 * @param {Array} favoritesArray
 * @return {Promise}
 */
FavoritesStore.prototype.saveFavoritesArray = function ( favoritesArray ) {
	// Update favoritesArray early, so that subsequent calls to this method use the new value,
	// but keep the old value available and roll back to it on error.
	const oldFavoritesArray = [ ...this.favoritesArray ];
	this.favoritesArray = [ ...favoritesArray ];
	const json = JSON.stringify( favoritesArray );
	return new mw.Api().saveOption( USER_PREFERENCE_NAME, json, { errorsuselocal: 1, errorformat: 'html' } )
		.then( () => {
			mw.user.options.set( USER_PREFERENCE_NAME, json );
		},
		( code, response ) => {
			// Restore previous value if the new one was not saved.
			this.favoritesArray = [ ...oldFavoritesArray ];
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
};

module.exports = FavoritesStore;
