const Column = require( './Column.js' );
const ColumnItem = require( './ColumnItem.js' );

/**
 * @class
 * @extends OO.ui.TabPanelLayout
 *
 * @constructor
 * @param {Object} [config] Configuration options.
 */
function ColumnGroup( config ) {
	config = Object.assign( {
		expanded: false,
		framed: true
	}, config );
	ColumnGroup.super.call( this, config );
	this.$element.addClass( 'ext-templatedata-ColumnGroup' );
	this.config = config;
	this.columns = [];
}

/* Setup */

OO.inheritClass( ColumnGroup, OO.ui.PanelLayout );

/* Events */

/**
 * When a template is chosen.
 *
 * @event choose
 * @param {Object} The template data of the chosen template.
 */

/* Methods */

ColumnGroup.prototype.addColumn = function ( column ) {
	column.connect( this, {
		select: this.onSelect,
		loadmore: this.onLoadmore
	} );
	this.$element.append( column.$element );
	this.columns.push( column );
};

ColumnGroup.prototype.getColumns = function () {
	return this.columns;
};

/**
 * @param {Column} column
 * @param {ColumnItem} columnItem
 */
ColumnGroup.prototype.onSelect = function ( column, columnItem ) {
	// Remove lower columns.
	this.columns
		.slice( this.columns.indexOf( column ) + 1 )
		.forEach( ( c ) => c.$element.remove() );
	// Add the new column.
	const col = new Column( { dataStore: this.config.dataStore } );
	col.connect( this, { choose: ( templatedata ) => {
		this.emit( 'choose', templatedata );
	} } );
	this.addColumn( col );
	if ( columnItem.getData().isCategory ) {
		col.loadItems( columnItem.getData().value ).then( () => {
			col.scrollElementIntoView();
		} );
	} else {
		const pageId = columnItem.getData().pageId;
		this.config.dataStore.getItemData( pageId ).then( ( itemData ) => {
			col.showTemplateDetails( column.getData().columnTitle, itemData );
			col.scrollElementIntoView();
		} );
	}
};

/**
 * @param {Column} column
 * @param {string} cmcontinue
 */
ColumnGroup.prototype.onLoadmore = function ( column, cmcontinue ) {
	column.loadItems( column.getData().columnTitle, cmcontinue );
};

module.exports = ColumnGroup;
