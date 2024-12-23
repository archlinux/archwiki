'use strict';

/*!
 * VisualEditor MWReferenceContextItem class.
 *
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

/**
 * Context item for a MWReference.
 *
 * @constructor
 * @extends ve.ui.LinearContextItem
 * @param {ve.ui.LinearContext} context Context the item is in
 * @param {ve.dm.Model} model Model the item is related to
 * @param {Object} [config]
 */
ve.ui.MWReferenceContextItem = function VeUiMWReferenceContextItem() {
	// Parent constructor
	ve.ui.MWReferenceContextItem.super.apply( this, arguments );
	this.view = null;
	/** @member {ve.dm.MWGroupReferences} */
	this.groupRefs = null;
	// Initialization
	this.$element.addClass( 've-ui-mwReferenceContextItem' );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWReferenceContextItem, ve.ui.LinearContextItem );

/* Static Properties */

ve.ui.MWReferenceContextItem.static.name = 'reference';

ve.ui.MWReferenceContextItem.static.icon = 'reference';

ve.ui.MWReferenceContextItem.static.label = OO.ui.deferMsg( 'cite-ve-dialogbutton-reference-title' );

ve.ui.MWReferenceContextItem.static.modelClasses = [ ve.dm.MWReferenceNode ];

ve.ui.MWReferenceContextItem.static.commandName = 'reference';

/* Methods */

/**
 * Get a DOM rendering of the reference.
 *
 * @private
 * @return {jQuery} DOM rendering of reference
 */
ve.ui.MWReferenceContextItem.prototype.getRendering = function () {
	const refNode = this.getReferenceNode();
	if ( refNode ) {
		this.view = new ve.ui.MWPreviewElement( refNode );

		// The $element property may be rendered into asynchronously, update the
		// context's size when the rendering is complete if that's the case
		this.view.once( 'render', this.context.updateDimensions.bind( this.context ) );

		return this.view.$element;
	} else {
		return $( '<div>' )
			.addClass( 've-ui-mwReferenceContextItem-muted' )
			.text( ve.msg( 'cite-ve-referenceslist-missingref' ) );
	}
};

/**
 * Get a DOM rendering of a warning if this reference is reused.
 *
 * @private
 * @return {jQuery|undefined}
 */
ve.ui.MWReferenceContextItem.prototype.getReuseWarning = function () {
	const listKey = this.model.getAttribute( 'listKey' );
	const totalUsageCount = this.groupRefs.getTotalUsageCount( listKey );

	if ( totalUsageCount > 1 ) {
		return $( '<div>' )
			.addClass( 've-ui-mwReferenceContextItem-muted' )
			.text( ve.msg( 'cite-ve-dialog-reference-editing-reused', totalUsageCount ) );
	}
};

/**
 * Get a DOM rendering of a warning if this reference is an extension.
 *
 * @private
 * @return {jQuery|undefined}
 */
ve.ui.MWReferenceContextItem.prototype.getExtendsWarning = function () {
	if ( this.model.getAttribute( 'extendsRef' ) ) {
		return $( '<div>' )
			.addClass( [
				've-ui-mwReferenceContextItem-muted',
				've-ui-mwReferenceContextItemSubNote'
			] )
			.text( ve.msg( 'cite-ve-dialog-reference-contextitem-extends' ) );
	}
};

/**
 * Get the reference node in the containing document (not the internal list document)
 *
 * @return {ve.dm.InternalItemNode|null} Reference item node
 */
ve.ui.MWReferenceContextItem.prototype.getReferenceNode = function () {
	if ( !this.model.isEditable() ) {
		return null;
	}
	if ( !this.referenceNode ) {
		this.referenceNode = this.groupRefs.getInternalModelNode( this.model.getAttribute( 'listKey' ) );
	}
	return this.referenceNode;
};

/**
 * @override
 */
ve.ui.MWReferenceContextItem.prototype.getDescription = function () {
	return this.model.isEditable() ? this.getRendering().text() : ve.msg( 'cite-ve-referenceslist-missingref' );
};

/**
 * Get the text of the parent reference.
 *
 * @private
 * @return {jQuery|null}
 */
ve.ui.MWReferenceContextItem.prototype.getParentRef = function () {
	const extendsRef = this.model.getAttribute( 'extendsRef' );
	if ( !extendsRef ) {
		return null;
	}
	const parentNode = this.groupRefs.getInternalModelNode( extendsRef );
	return parentNode ? new ve.ui.MWPreviewElement( parentNode, { useView: true } ).$element :
		$( '<div>' )
			.addClass( 've-ui-mwReferenceContextItem-muted' )
			.text( ve.msg( 'cite-ve-dialog-reference-missing-parent-ref' ) );
};

/**
 * @override
 */
ve.ui.MWReferenceContextItem.prototype.setup = function () {
	this.groupRefs = ve.dm.MWDocumentReferences.static.refsForDoc( this.getFragment().getDocument() )
		.getGroupRefs( this.model.getAttribute( 'listGroup' ) );

	// Parent method
	return ve.ui.MWReferenceContextItem.super.prototype.setup.apply( this, arguments );
};

/**
 * @override
 */
ve.ui.MWReferenceContextItem.prototype.renderBody = function () {
	this.$body.empty().append(
		this.getParentRef(),
		this.getExtendsWarning(),
		this.getRendering(),
		this.getReuseWarning()
	);
};

/**
 * @override
 */
ve.ui.MWReferenceContextItem.prototype.teardown = function () {
	if ( this.view ) {
		this.view.destroy();
	}

	// Call parent
	ve.ui.MWReferenceContextItem.super.prototype.teardown.call( this );
};

/* Registration */

ve.ui.contextItemFactory.register( ve.ui.MWReferenceContextItem );
