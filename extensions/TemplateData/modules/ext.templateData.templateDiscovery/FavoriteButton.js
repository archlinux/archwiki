const FavoritesStore = require( './FavoritesStore.js' );

/**
 * @class
 * @extends OO.ui.ButtonInputWidget
 *
 * @constructor
 * @param {Object} config Configuration options.
 * @param {string} config.pageId The wiki page ID of the page to favorite/unfavorite. If `"-1"`
 * then the button will be disabled.
 * @param {FavoritesStore} config.favoritesStore The store to use.
 */
function FavoriteButton( config ) {
	this.pageId = config.pageId;
	this.favoritesStore = config.favoritesStore || new FavoritesStore();
	this.isFavorite = this.favoritesStore.isFavorite( this.pageId );
	const label = mw.msg( this.isFavorite ? 'templatedata-favorite-remove' : 'templatedata-favorite-add' );
	config = Object.assign( {
		icon: this.isFavorite ? 'bookmark' : 'bookmarkOutline',
		framed: false,
		invisibleLabel: true,
		label: label,
		title: label,
		type: 'button'
	}, config );
	FavoriteButton.super.call( this, config );

	// Don't let temp and anon users favorite.
	if ( !mw.user.isNamed() ) {
		this.setDisabled( true );
		this.setTitle( mw.msg( 'templatedata-favorite-disabled' ) );
	}

	// Configure non-existing templates.
	if ( config.pageId === '-1' ) {
		this.setDisabled( true );
		this.setLabel( '' );
	}
}

/* Setup */

OO.inheritClass( FavoriteButton, OO.ui.ButtonInputWidget );

/* Methods */

FavoriteButton.prototype.onClick = function () {
	if ( !this.isFavorite ) {
		// Add to favorites
		this.favoritesStore.addFavorite( this.pageId ).then( () => {
			this.isFavorite = true;
			this.setIcon( 'bookmark' );
			this.setLabel( mw.msg( 'templatedata-favorite-remove' ) );
			this.setTitle( this.getLabel() );
		}, () => {} );
	} else {
		// Remove from favorites
		this.favoritesStore.removeFavorite( this.pageId ).then( () => {
			this.isFavorite = false;
			this.setIcon( 'bookmarkOutline' );
			this.setLabel( mw.msg( 'templatedata-favorite-add' ) );
			this.setTitle( this.getLabel() );
		} );
	}
};

module.exports = FavoriteButton;
