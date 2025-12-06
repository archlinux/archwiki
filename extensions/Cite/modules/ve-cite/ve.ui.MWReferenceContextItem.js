'use strict';

/*!
 * VisualEditor MWReferenceContextItem class.
 *
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

const MWDocumentReferences = require( './ve.dm.MWDocumentReferences.js' );
const MWReferenceModel = require( './ve.dm.MWReferenceModel.js' );
const MWReferenceNode = require( './ve.dm.MWReferenceNode.js' );
const Options = require( './ve.ui.MWSubReferenceHelpDialogOptions.js' );

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
	/** @member {ve.ui.MWPreviewElement} */
	this.view = null;
	/** @member {ve.ui.MWPreviewElement} */
	this.detailsView = null;
	/** @member {ve.dm.MWGroupReferences} */
	this.groupRefs = null;
	// Initialization
	this.$element.addClass( 've-ui-mwReferenceContextItem' );

	this.showHelp = !OO.ui.isMobile() &&
		!Options.loadBoolean( 'hide-subref-help' );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWReferenceContextItem, ve.ui.LinearContextItem );

/* Static Properties */

ve.ui.MWReferenceContextItem.static.name = 'reference';

ve.ui.MWReferenceContextItem.static.icon = 'reference';

ve.ui.MWReferenceContextItem.static.label = OO.ui.deferMsg( 'cite-ve-dialogbutton-reference-title' );

ve.ui.MWReferenceContextItem.static.modelClasses = [ MWReferenceNode ];

ve.ui.MWReferenceContextItem.static.commandName = 'reference';

/* Methods */

/**
 * Get a DOM rendering of a normal reference, or the main ref for a details
 * reference.
 *
 * @private
 * @return {jQuery} DOM rendering of reference
 */
ve.ui.MWReferenceContextItem.prototype.getMainRefPreview = function () {
	// Render a placeholder for missing refs.
	let refNode = this.getReferenceNode();
	let errorMsgKey = 'cite-ve-referenceslist-missingref';

	// Render main ref if this is a subref, or a placeholder if missing.
	const mainRefKey = this.model.getAttribute( 'mainRefKey' );
	if ( mainRefKey && refNode ) {
		refNode = this.groupRefs.getInternalModelNode( mainRefKey );
		errorMsgKey = 'cite-ve-dialog-reference-missing-parent-ref';
	}

	if ( !refNode ) {
		return $( '<div>' )
			.addClass( 've-ui-mwReferenceContextItem-muted' )
			// The following messages are used here:
			// * cite-ve-referenceslist-missingref
			// * cite-ve-dialog-reference-missing-parent-ref
			.text( ve.msg( errorMsgKey ) );
	}

	// Render normal ref.
	this.view = new ve.ui.MWPreviewElement( refNode, { useView: true } );
	// The $element property may be rendered into asynchronously, update the
	// context's size when the rendering is complete if that's the case
	this.view.once( 'render', this.context.updateDimensions.bind( this.context ) );

	return this.view.$element;
};

/**
 * Get a preview of the reference details.
 *
 * @private
 * @return {jQuery|undefined}
 */
ve.ui.MWReferenceContextItem.prototype.getDetailsPreview = function () {
	if ( !this.model.getAttribute( 'mainRefKey' ) ) {
		return;
	}

	const editDetails = new OO.ui.Layout( {
		classes: [ 've-ui-mwReferenceContextItem-subrefHeader' ],
		content: [
			new OO.ui.ButtonWidget(
				{
					framed: false,
					invisibleLabel: true,
					icon: this.isReadOnly() ? 'eye' : 'edit',
					label: ve.msg( this.isReadOnly() ?
						'visualeditor-contextitemwidget-label-view' :
						'visualeditor-contextitemwidget-label-secondary'
					),
					classes: [ 've-ui-mwReferenceContextItem-editButton' ]
				}
			).on( 'click', () => {
				// Phabricator T396734
				ve.track( 'activity.subReference', { action: 'context-edit-details' } );
				return this.onEditSubref();
			} )
		]
	} );

	this.detailsView = new ve.ui.MWPreviewElement( this.getReferenceNode(), { useView: true } );
	// The $element property may be rendered into asynchronously, update the
	// context's size when the rendering is complete if that's the case
	this.detailsView.once( 'render', this.context.updateDimensions.bind( this.context ) );

	return new OO.ui.HorizontalLayout( {
		classes: [ 've-ui-mwReferenceContextItem-detailsPreview' ],
		items: [ this.detailsView, editDetails ]
	} ).$element;
};

/**
 * Override default edit button, when a subref is present.
 */
ve.ui.MWReferenceContextItem.prototype.onEditButtonClick = function () {
	const mainRefKey = this.model.getAttribute( 'mainRefKey' );
	if ( !mainRefKey ) {
		ve.ui.LinearContextItem.prototype.onEditButtonClick.apply( this );
		return;
	}

	// Edit the main ref--like when editing a list-defined ref!
	// TODO: Make this into a reusable command.
	const groupRefs = MWDocumentReferences.static
		.refsForDoc( this.getFragment().getDocument() )
		.getGroupRefs( this.model.getAttribute( 'listGroup' ) );
	const mainRefNode = groupRefs.getRefNode( mainRefKey );
	const mainModelItem = ve.ui.contextItemFactory.getRelatedItems( [ mainRefNode ] )
		.find( ( item ) => item.name !== 'mobileActions' );

	if ( mainModelItem ) {
		const mainContextItem = ve.ui.contextItemFactory.lookup( mainModelItem.name );
		if ( mainContextItem ) {
			const surface = this.context.getSurface();
			const command = surface.commandRegistry.lookup( mainContextItem.static.commandName );
			const fragmentArgs = {
				fragment: surface.getModel().getLinearFragment(
					mainRefNode.getOuterRange(),
					true
				),
				selectFragmentOnClose: false
			};
			const newArgs = ve.copy( command.args );
			if ( command.name === 'reference' ) {
				newArgs[ 1 ] = fragmentArgs;
			} else {
				ve.extendObject( newArgs[ 0 ], fragmentArgs );
			}
			command.execute( surface, newArgs, 'context' );
		}
	}
};

/**
 * @private
 */
ve.ui.MWReferenceContextItem.prototype.onEditSubref = function () {
	ve.ui.LinearContextItem.prototype.onEditButtonClick.apply( this );
};

/**
 * Get a DOM rendering of a warning if this reference is reused.
 *
 * @private
 * @return {jQuery|undefined}
 */
ve.ui.MWReferenceContextItem.prototype.getReuseWarning = function () {
	const listKey = this.model.getAttribute( 'mainRefKey' ) || this.model.getAttribute( 'listKey' );
	const totalUsageCount = this.groupRefs.getTotalUsageCount( listKey );

	if ( totalUsageCount <= 1 ) {
		return;
	}

	if ( !mw.config.get( 'wgCiteSubReferencing' ) ) {
		return $( '<div>' )
			.addClass( 've-ui-mwReferenceContextItem-muted' )
			.text( ve.msg( 'cite-ve-dialog-reference-editing-reused', totalUsageCount ) );
	}

	return new OO.ui.Layout( {
		classes: [ 've-ui-mwReferenceContextItem-reuse-layout' ],
		content: [
			new OO.ui.LabelWidget( {
				classes: [ 've-ui-mwReferenceContextItem-reuse' ],
				label: ve.msg( 'cite-ve-dialog-reference-editing-reused-short', totalUsageCount )
			} )
		]
	} ).$element;
};

/**
 * Get a DOM rendering of a button to add details.
 *
 * @private
 * @return {jQuery|undefined}
 */
ve.ui.MWReferenceContextItem.prototype.getAddDetailsButton = function () {
	if ( !mw.config.get( 'wgCiteSubReferencing' ) || this.model.getAttribute( 'mainRefKey' ) ) {
		return;
	}

	const listKey = this.model.getAttribute( 'listKey' );
	if ( this.groupRefs.getTotalUsageCount( listKey ) < 2 ) {
		return;
	}

	const openAddDetailsDialog = () => {
		// Phabricator T396734
		ve.track( 'activity.subReference', { action: 'context-add-details' } );

		const ref = MWReferenceModel.static.newFromReferenceNode( this.model );
		ve.ui.commandRegistry.lookup( 'reference' ).execute(
			this.context.getSurface(),
			// Arguments for calling ve.ui.MWReferenceDialog.getSetupProcess()
			[ 'reference', { createSubRef: ref } ],
			'context'
		);
	};

	const button = new OO.ui.ButtonWidget( {
		label: ve.msg( 'cite-ve-dialog-reference-add-details-button' ),
		classes: [ 've-ui-mwReferenceContextItem-addDetailsButton' ],
		framed: false,
		icon: 'add'
	} ).on( 'click', () => {
		if ( !this.showHelp ) {
			openAddDetailsDialog();
			return;
		}

		const windowManager = this.context.getSurface().getDialogs();
		windowManager.openWindow( 'subrefHelp' ).closing.then( ( action ) => {
			if ( action === 'dismiss' ) {
				this.showHelp = false;
				Options.saveBoolean( 'hide-subref-help', true );
				button.$element.find( '.mw-pulsating-dot' ).remove();
				openAddDetailsDialog();
			}
		} );
	} );

	if ( this.showHelp ) {
		button.$element.append( $( '<span>' ).addClass( 'mw-pulsating-dot' ) );
	}

	return button.$element;
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
	return this.model.isEditable() ? this.getMainRefPreview().text() : ve.msg( 'cite-ve-referenceslist-missingref' );
};

/**
 * @override
 */
ve.ui.MWReferenceContextItem.prototype.setup = function () {
	this.groupRefs = MWDocumentReferences.static.refsForDoc( this.getFragment().getDocument() )
		.getGroupRefs( this.model.getAttribute( 'listGroup' ) );

	// Parent method
	return ve.ui.MWReferenceContextItem.super.prototype.setup.apply( this, arguments );
};

/**
 * @override
 */
ve.ui.MWReferenceContextItem.prototype.renderBody = function () {
	const detailsPreview = this.getDetailsPreview();
	const detailsButton = this.getAddDetailsButton();
	// attach reuse warning to a different place for mobile
	const mainPreview = this.context.isMobile() ?
		[ this.getMainRefPreview(), this.getReuseWarning() ] :
		[ this.getReuseWarning(), this.getMainRefPreview() ];

	const $detailsSeparator = $( '<div>' )
		.addClass( 've-ui-mwReferenceContextItem-addDetailsSeparator' );

	this.$body.empty().append(
		mainPreview,
		detailsPreview || detailsButton ? $detailsSeparator : null,
		detailsPreview,
		detailsButton
	);
};

/**
 * @override
 */
ve.ui.MWReferenceContextItem.prototype.teardown = function () {
	if ( this.view ) {
		this.view.destroy();
	}
	if ( this.detailsView ) {
		this.detailsView.destroy();
	}

	// Call parent
	ve.ui.MWReferenceContextItem.super.prototype.teardown.call( this );
};

module.exports = ve.ui.MWReferenceContextItem;
