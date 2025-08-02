const FavoritesStore = require( './FavoritesStore.js' );
const FavoriteButton = require( './FavoriteButton.js' );

/**
 * @class
 * @extends OO.ui.MenuOptionWidget
 *
 * @constructor
 * @param {Object} config
 * @param {string} config.data.title Page title of the template
 * @param {jQuery|string} [config.description=''] Search result description
 * @param {string} [config.data.redirecttitle] Page title for the "redirected from" message
 * @param {FavoritesStore} favoritesStore
 */
function TemplateMenuItem( config, favoritesStore ) {
	config = Object.assign( {
		classes: [ 'ext-templatedata-TemplateMenuItem' ],
		$label: $( '<a>' )
	}, config );
	TemplateMenuItem.super.call( this, config );

	this.data = config.data;
	if ( config.data.redirecttitle ) {
		const redirecttitle = new mw.Title( config.data.redirecttitle )
			.getRelativeText( mw.config.get( 'wgNamespaceIds' ).template );
		$( '<span>' )
			.addClass( 'ext-templatedata-search-redirectedfrom' )
			.text( mw.msg( 'redirectedfrom', redirecttitle ) )
			.appendTo( this.$element );
	}
	// Make the label a link, but only functional for 'open in new tab'.
	this.$label.attr( 'href', mw.util.getUrl( config.data.title ) );
	this.$label.on( 'click', ( event ) => {
		event.preventDefault();
	} );
	// Main area click handler (includes clicks on the child $label).
	this.$element.on( 'click', this.onClick.bind( this ) );

	$( '<span>' )
		.addClass( 'ext-templatedata-search-description' )
		.append( $( '<bdi>' ).text( config.description || '' ) )
		.appendTo( this.$element );

	// Add a wrapper element so that the button and the other elements are in separate containers.
	const $wrap = $( '<span>' );
	$wrap.append( this.$element.contents() );
	this.$element.append( $wrap );

	this.favoriteButton = new FavoriteButton( {
		favoritesStore: favoritesStore,
		pageId: config.data.pageId
	} );
	this.$element.append( this.favoriteButton.$element );

	// Configure non-existing templates.
	if ( config.data.pageId === '-1' ) {
		this.favoriteButton.setDisabled( true );
		this.$label.addClass( 'new' );
	}
}

/* Setup */

OO.inheritClass( TemplateMenuItem, OO.ui.MenuOptionWidget );

/* Events */

/**
 * When a template is chosen.
 *
 * @event choose
 * @param {Object} The template data of the chosen template.
 */

/* Methods */

TemplateMenuItem.prototype.onClick = function ( event ) {
	// Only handle click events that do not belong to the favorite button.
	if ( !this.favoriteButton.$element[ 0 ].contains( event.target ) ) {
		event.preventDefault();
		this.emit( 'choose', this.data );
	}
};

module.exports = TemplateMenuItem;
