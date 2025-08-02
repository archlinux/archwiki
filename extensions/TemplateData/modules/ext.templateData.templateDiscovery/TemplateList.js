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
		expanded: false
	}, config );
	TemplateList.super.call( this, 'template-list', config );
	this.$element.addClass( 'ext-templatedata-TemplateList' );
	this.menuItems = new Map();
	this.config = config;
	this.menu = new OO.ui.PanelLayout( { expanded: false } );

	this.config.favoritesStore.getAllFavoritesDetails().then( ( favorites ) => {
		// Either loop through all favorites, adding them to the list.
		for ( const fave of favorites ) {
			this.addRowToList( fave );
		}
		// Or add a message explaining that there are no favorites.
		if ( favorites.length === 0 ) {
			this.menu.$element.append( $( '<p>' )
				.addClass( 'ext-templatedata-TemplateList-empty' )
				.text( mw.msg( 'templatedata-search-list-empty' ) ) );
		}
		// Then add the list (or message) to the container.
		this.$element.append( this.menu.$element );
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

/* Events */

/**
 * When a template is chosen from the list of favorites.
 *
 * @event choose
 * @param {Object} The template data of the chosen template.
 */

/* Methods */

TemplateList.prototype.setupTabItem = function () {
	const icon = new OO.ui.IconWidget( {
		icon: 'bookmark',
		framed: false,
		flags: [ 'progressive' ],
		classes: [ 'ext-templatedata-TemplateList-tabIcon' ]
	} );
	this.tabItem.$label.append(
		icon.$element,
		' ',
		mw.msg( 'templatedata-search-list-header' )
	);
};

TemplateList.prototype.onChoose = function ( templateData ) {
	this.emit( 'choose', templateData );
};

/**
 * When a favorite is removed, update the list.
 *
 * @param {number} pageId
 */
TemplateList.prototype.onFavoriteRemoved = function ( pageId ) {
	this.menuItems.get( pageId ).$element[ 0 ].classList.add( 'ext-templatedata-TemplateMenuItem-removed' );
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
		this.menuItems.get( pageId ).$element[ 0 ].classList.remove( 'ext-templatedata-TemplateMenuItem-removed' );
		return;
	}

	// Otherwise, add it to the list.
	this.config.favoritesStore.getFavoriteDetail( pageId ).then( ( data ) => {
		if ( data && data[ 0 ] ) {
			this.addRowToList( data[ 0 ] );
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
		description: fave.description
	};
	const templateMenuItem = new TemplateMenuItem( searchResultConfig, this.config.favoritesStore );
	this.menuItems.set( fave.pageId, templateMenuItem );
	templateMenuItem.connect( this, { choose: 'onChoose' } );
	this.menu.$element.append( templateMenuItem.$element );
};

module.exports = TemplateList;
