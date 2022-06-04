/*!
 * VisualEditor MWAlienAnnotationContextItem class.
 *
 * @copyright 2011-2021 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * Context item for a MWAlienAnnotation
 *
 * @class
 * @extends ve.ui.MWAnnotationContextItem
 *
 * @constructor
 * @param {ve.ui.Context} context Context item is in
 * @param {ve.dm.Model} model Model item is related to
 * @param {Object} config Configuration options
 */
ve.ui.MWAlienAnnotationContextItem = function VeUiMWAlienAnnotationContextItem() {
	// Parent constructor
	ve.ui.MWAlienAnnotationContextItem.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWAlienAnnotationContextItem, ve.ui.MWAnnotationContextItem );

/* Static Properties */

ve.ui.MWAlienAnnotationContextItem.static.name = 'mwAlienAnnotation';

ve.ui.MWAlienAnnotationContextItem.static.modelClasses = [
	ve.dm.MWAlienAnnotationNode
];

/* Methods */

ve.ui.MWAlienAnnotationContextItem.prototype.getLabelMessage = function () {
	var type = this.model.getAttribute( 'type' );
	if ( type.indexOf( '/End', type.length - 4 ) !== -1 ) {
		return mw.message( 'visualeditor-annotations-default-end' ).text();
	} else {
		return mw.message( 'visualeditor-annotations-default-start' ).text();
	}
};

ve.ui.MWAlienAnnotationContextItem.prototype.getDescriptionMessage = function () {
	var type = this.model.getAttribute( 'type' );
	if ( type.indexOf( '/End', type.length - 4 ) !== -1 ) {
		return '';
	}
	return mw.message( 'visualeditor-annotations-default-description' ).parseDom();
};

ve.ui.contextItemFactory.register( ve.ui.MWAlienAnnotationContextItem );
