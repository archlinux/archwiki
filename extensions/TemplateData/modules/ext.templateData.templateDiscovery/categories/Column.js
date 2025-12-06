const ColumnItem = require( './ColumnItem.js' );

/**
 * @class
 * @extends OO.ui.PanelLayout
 *
 * @constructor
 * @param {Object} [config] Configuration options.
 */
function Column( config ) {
	config = Object.assign( {
		expanded: false,
		framed: true
	}, config );
	Column.super.call( this, config );
	OO.ui.mixin.PendingElement.call( this, config );
	OO.EventEmitter.call( this );

	this.$element.addClass( 'ext-templatedata-ColumnGroup-Column' );

	this.dataStore = config.dataStore;
	this.items = [];
	this.data = {};
}

/* Setup */

OO.inheritClass( Column, OO.ui.PanelLayout );
OO.mixinClass( Column, OO.ui.mixin.PendingElement );
OO.mixinClass( Column, OO.EventEmitter );

/* Events */

/**
 * When an item in this column is selected.
 *
 * @event select
 * @param {Column} column
 * @param {ColumnItem} columnItem
 */

/**
 * When a "load more" item is clicked.
 *
 * @event loadmore
 * @param {Column} column
 * @param {string} cmcontinue
 */

/**
 * When a template is chosen.
 *
 * @event choose
 * @param {Object} The template data of the chosen template.
 */

/** Methods */

/**
 * @param {string} columnTitle
 * @param {string} cmcontinue
 * @return {Promise}
 */
Column.prototype.loadItems = function ( columnTitle, cmcontinue ) {
	this.data.columnTitle = columnTitle;
	this.scrollElementIntoView();
	this.pushPending();
	return this.dataStore.getColumnData( columnTitle, cmcontinue )
		.then( ( data ) => {
			for ( const itemData of data ) {
				const columnItem = new ColumnItem( itemData );
				columnItem.connect( this, {
					select: this.onSelect,
					loadmore: this.onLoadmore
				} );
				this.items.push( columnItem );
				this.$element.append( columnItem.$element );
			}
			if ( data.length === 0 ) {
				this.$element.addClass( 'ext-templatedata-column-info' );
				this.$element.append( mw.message( 'templatedata-category-column-empty', columnTitle ).parse() );
			}
			this.popPending();
		} ).catch( ( err ) => {
			this.$element.addClass( 'ext-templatedata-column-info ext-templatedata-column-error' );
			this.$element.text( mw.msg( 'templatedata-category-column-error' ) + mw.msg( 'colon-separator' ) + err );
			this.popPending();
		} );
	// @todo popPending should be moved to .finally(), when we can use that.
};

Column.prototype.showTemplateDetails = function ( catTitle, info ) {
	this.$element.empty();
	this.$element.addClass( 'ext-templatedata-column-info' );
	const $link = $( '<a>' )
		.addClass( 'ext-templatedata-column-info-title' )
		.attr( 'title', info.templatedata.title )
		.attr( 'href', mw.util.getUrl( info.templatedata.title ) )
		.append( info.templatedata.title );
	const $description = $( '<span>' )
		.addClass( 'ext-templatedata-description' )
		.append( $( '<bdi>' ).text( info.templatedata.description || '' ) );
	const button = new OO.ui.ButtonWidget( {
		label: mw.msg( 'templatedata-category-use-template' ),
		flags: 'progressive'
	} );
	button.connect( this, { click: () => {
		this.emit( 'choose', info.templatedata );
	} } );
	const $cats = $( '<p>' ).addClass( 'ext-templatedata-categories' );
	const catsLabel = mw.msg( 'templatedata-category-categories' ) + mw.msg( 'colon-separator' );
	$cats.append( $( '<em>' ).text( catsLabel ) );
	// Exclude the currently-selected category.
	const otherCats = info.categories.categories
		.filter( ( c ) => c.title !== catTitle && !c.hidden );
	otherCats.forEach( ( catData, catIndex ) => {
		const $cat = $( '<span>' ).text( new mw.Title( catData.title ).getMainText() );
		$cats.append( $cat );
		if ( catIndex !== otherCats.length - 1 ) {
			$cats.append( mw.message( 'semicolon-separator' ).escaped() );
		}
	} );
	// If the template is in no other categories, say so.
	if ( otherCats.length === 0 ) {
		$cats.append( mw.message( 'templatedata-category-no-cats' ).escaped() );
	}
	this.$element.append( $link, $description, button.$element, $cats );
};

Column.prototype.getData = function () {
	return this.data;
};

Column.prototype.getItem = function ( index ) {
	return this.items[ index ];
};

Column.prototype.onSelect = function ( columnItem ) {
	// Unhighlight all other items.
	this.items
		.filter( ( i ) => i !== columnItem )
		.forEach( ( i ) => i.setHighlighted( false ) );
	this.emit( 'select', this, columnItem );
};

Column.prototype.onLoadmore = function ( columnItem, cmcontinue ) {
	columnItem.$element.remove();
	this.items.splice( this.items.indexOf( columnItem ), 1 );
	this.emit( 'loadmore', this, cmcontinue );
};

module.exports = Column;
