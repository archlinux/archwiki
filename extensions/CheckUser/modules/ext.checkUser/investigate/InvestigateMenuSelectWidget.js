/**
 * Investigate Menu Select Widget
 *
 * @class
 *
 * @constructor
 * @param {Object} [config] Configuration options
 */

const InvestigateMenuSelectWidget = function ( config ) {
	// Parent constructor
	InvestigateMenuSelectWidget.super.call( this, config );
};

/* Setup */

OO.inheritClass( InvestigateMenuSelectWidget, OO.ui.MenuSelectWidget );

/**
 * @inheritdoc
 */
InvestigateMenuSelectWidget.prototype.onDocumentMouseUp = function ( e ) {
	if ( !this.selecting ) {
		const item = this.findTargetItem( e );
		if ( item && item.isSelectable() ) {
			this.selecting = item;
		}
	}
	if ( !this.isDisabled() && e.which === OO.ui.MouseButtons.LEFT && this.selecting ) {
		this.emit( 'investigate', this.selecting );
	}
	return InvestigateMenuSelectWidget.super.prototype.onDocumentMouseUp.call( this, e );
};

/**
 * @inheritdoc
 */
InvestigateMenuSelectWidget.prototype.onDocumentKeyDown = function ( e ) {
	const selected = this.findSelectedItems(),
		currentItem = this.findHighlightedItem() || (
			Array.isArray( selected ) ? selected[ 0 ] : selected
		);

	if ( e.keyCode === OO.ui.Keys.ENTER ) {
		this.emit( 'investigate', currentItem );
	}

	return InvestigateMenuSelectWidget.super.prototype.onDocumentKeyDown.call( this, e );
};

module.exports = InvestigateMenuSelectWidget;
