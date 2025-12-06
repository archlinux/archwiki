/**
 * @class
 * @extends OO.ui.TabPanelLayout
 *
 * @constructor
 * @param {Object} [config] Configuration options.
 */
function ColumnItem( config ) {
	config = Object.assign( {
		expanded: false,
		$label: config.data.cmcontinue !== undefined ? null : $( '<a>' )
	}, config );
	ColumnItem.super.call( this, config );
	OO.EventEmitter.call( this );

	this.$element.addClass( 'ext-templatedata-ColumnItem' );
	this.config = config;
	this.column = config.column;
	this.$element.on( 'click', this.onClick.bind( this ) );
	this.isLoadMore = config.data.cmcontinue !== undefined;
	if ( this.isLoadMore ) {
		this.$element.addClass( 'ext-templatedata-ColumnItem-loadmore' );
	} else {
		this.$label.attr( 'title', this.config.label );
		this.$label.attr( 'href', mw.util.getUrl( config.data.value ) );
	}
}

/* Setup */

OO.inheritClass( ColumnItem, OO.ui.MenuOptionWidget );
OO.mixinClass( ColumnItem, OO.EventEmitter );

/* Static Properties */

ColumnItem.static.scrollIntoViewOnSelect = true;

/* Events */

/**
 * When the item is selected.
 *
 * @event select
 * @param {Object} The template data of the chosen template.
 */

/* Methods */

ColumnItem.prototype.onClick = function ( event ) {
	event.preventDefault();
	if ( this.isLoadMore ) {
		this.emit( 'loadmore', this, this.getData().cmcontinue );
	} else {
		this.setHighlighted( true );
		this.emit( 'select', this );
	}
};

module.exports = ColumnItem;
