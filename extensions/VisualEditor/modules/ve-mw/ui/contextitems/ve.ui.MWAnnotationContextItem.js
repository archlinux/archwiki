/*!
 * VisualEditor MWAnnotationContextItem class.
 *
 * @copyright 2011-2021 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * Context item for a MWAnnotation
 *
 * @class
 * @extends ve.ui.LinearContextItem
 *
 * @constructor
 * @param {ve.ui.Context} context Context item is in
 * @param {ve.dm.Model} model Model item is related to
 * @param {Object} config Configuration options
 */
ve.ui.MWAnnotationContextItem = function VeUiMWAnnotationContextItem() {
	// Parent constructor
	ve.ui.MWAnnotationContextItem.super.apply( this, arguments );

	// Initialization
	this.$element.addClass( 've-ui-mwAnnotationContextItem' );

	this.setLabel( this.getLabelMessage() );

	this.$actions.remove();
};

/* Inheritance */

OO.inheritClass( ve.ui.MWAnnotationContextItem, ve.ui.LinearContextItem );

/* Static Properties */

ve.ui.MWAnnotationContextItem.static.editable = false;

ve.ui.MWAnnotationContextItem.static.name = 'mwAnnotation';

ve.ui.MWAnnotationContextItem.static.icon = 'markup';

ve.ui.MWAnnotationContextItem.static.modelClasses = [
	ve.dm.MWAnnotationNode
];

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.MWAnnotationContextItem.prototype.renderBody = function () {
	this.$body.empty();

	var $desc = this.getDescriptionMessage();
	if ( $desc ) {
		this.$body.append( $desc, $( document.createTextNode( mw.msg( 'word-separator' ) ) ) );
	}

	if ( this.model.getAttribute( 'mw' ) ) {
		if ( this.model.getAttribute( 'mw' ).extendedRange ) {
			// eslint-disable-next-line no-jquery/no-append-html
			this.$body.append( mw.message( 'visualeditor-annotations-extended-documentation' ).parseDom() );
		}
	}
};
