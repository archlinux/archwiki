const FavoritesStore = require( './FavoritesStore.js' );
const TemplateMenuItem = require( './TemplateMenuItem.js' );

/**
 * @class
 * @extends OO.ui.TabPanelLayout
 *
 * @constructor
 * @param {Object} [config] Configuration options.
 * @param {FavoritesStore} config.favoritesStore
 */
function TemplateList( config ) {
	config = Object.assign( {
		label: mw.msg( 'templatedata-search-list-header' ),
		expanded: false
	}, config );
	TemplateList.super.call( this, 'template-list', config );
	OO.ui.mixin.DraggableGroupElement.call( this );
	this.$element.append( this.$group );

	this.$element.addClass( 'ext-templatedata-TemplateList' );
	this.menuItems = new Map();
	this.config = config;
	this.emptyListMessage = null;
	this.favorites = [];
	this.favoritesStore = config.favoritesStore || new FavoritesStore();
	this.templateCount = null;
	this.$templateCountWrapper = null;

	this.config.favoritesStore.getAllFavoritesDetails().then( ( favorites ) => {
		// Either loop through all favorites, adding them to the list.
		for ( const fave of favorites ) {
			this.addRowToList( fave );
		}
		// Or add a message explaining that there are no favorites.
		if ( favorites.length === 0 ) {
			const emptyListMessageLabel = mw.user.isNamed() ?
				mw.msg( 'templatedata-search-list-empty' ) :
				mw.msg( 'templatedata-search-list-empty-anon' );
			this.emptyListMessage = new OO.ui.MessageWidget( {
				icon: 'bookmark',
				classes: [ 'ext-templatedata-TemplateList-empty' ],
				label: emptyListMessageLabel
			} );
			this.$group.append( this.emptyListMessage.$element );
		} else {
			this.$templateCountWrapper = $( '<div>' );
			this.templateCount = new OO.ui.MessageWidget( {
				classes: [ 'ext-templatedata-TemplateList-count' ],
				label: mw.msg(
					'templatedata-favorite-count',
					favorites.length,
					this.favoritesStore.maxFavorites
				)
			} );
			this.$templateCountWrapper.append( this.templateCount.$element );
			this.$element.prepend( this.$templateCountWrapper );
		}

	} );

	this.config.favoritesStore.connect(
		this,
		{
			removed: 'onFavoriteRemoved',
			added: 'onFavoriteAdded'
		}
	);

}

/* Setup */

OO.inheritClass( TemplateList, OO.ui.TabPanelLayout );
OO.mixinClass( TemplateList, OO.ui.mixin.DraggableGroupElement );

/* Events */

/**
 * When a template is chosen from the list of favorites.
 *
 * @event choose
 * @param {Object} The template data of the chosen template.
 */

/* Methods */

TemplateList.prototype.onChoose = function ( templateData ) {
	this.emit( 'choose', templateData );
};

/**
 * When a favorite is removed, update the list.
 *
 * @param {number} pageId
 */
TemplateList.prototype.onFavoriteRemoved = function ( pageId ) {
	this.menuItems.get( pageId ).toggleFavorited( false );
	// Remove from favorites array
	const index = this.favorites.indexOf( parseInt( pageId ) );
	if ( index > -1 ) {
		this.favorites.splice( index, 1 );
	}
	this.updateFavoritesCount();
};

/**
 * When a favorite is added, update the list.
 *
 * @param {number} pageId
 * @return {void}
 */
TemplateList.prototype.onFavoriteAdded = function ( pageId ) {
	// Check if the pageId is already in the list.
	// If it is, remove the 'removed' class.
	if ( this.menuItems.has( pageId ) ) {
		this.menuItems.get( pageId ).toggleFavorited( true );
		// Add back to favorites array if not already there
		if ( !this.favorites.includes( parseInt( pageId ) ) ) {
			this.favorites.push( parseInt( pageId ) );
		}
		this.updateFavoritesCount();
		return;
	}

	// Otherwise, add it to the list.
	this.config.favoritesStore.getFavoritesDetails( [ pageId ] ).then( ( data ) => {
		if ( data && data[ 0 ] ) {
			this.addRowToList( data[ 0 ] );
			this.updateFavoritesCount();
		}
	} );
};

/**
 * Add a template to the list of favorites.
 *
 * @param {Object} fave
 */
TemplateList.prototype.addRowToList = function ( fave ) {
	const templateNsId = mw.config.get( 'wgNamespaceIds' ).template;
	const searchResultConfig = {
		data: fave,
		label: mw.Title.newFromText( fave.title ).getRelativeText( templateNsId ),
		description: fave.description,
		draggable: true // Only allow reordering of favorites in the list.
	};
	const templateMenuItem = new TemplateMenuItem( searchResultConfig, this.favoritesStore );
	this.addItems( templateMenuItem );
	this.favorites.push( parseInt( fave.pageId ) );
	this.menuItems.set( fave.pageId, templateMenuItem );
	templateMenuItem.connect( this, { choose: 'onChoose', drop: 'onReorder' } );
	// Remove the empty-list state (if applicable).
	if ( this.emptyListMessage ) {
		this.emptyListMessage.$element.remove();
		this.emptyListMessage = null;
	}
};

TemplateList.prototype.onReorder = function ( event ) {
	const reorderedPageId = parseInt( event.data.pageId );
	const oldIndex = this.favorites.indexOf( reorderedPageId );
	this.favorites.splice( oldIndex, 1 );
	this.favorites.splice( event.index, 0, reorderedPageId );
	this.favoritesStore.saveFavoritesArray( this.favorites );
};

/**
 * Update the favorites count display.
 */
TemplateList.prototype.updateFavoritesCount = function () {
	if ( this.templateCount ) {
		this.templateCount.setLabel( mw.msg(
			'templatedata-favorite-count',
			this.favorites.length,
			this.favoritesStore.maxFavorites
		) );
	}
};

module.exports = TemplateList;
