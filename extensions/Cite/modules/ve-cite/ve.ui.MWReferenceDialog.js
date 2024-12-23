'use strict';

/*!
 * VisualEditor UserInterface MediaWiki MWReferenceDialog class.
 *
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

/**
 * Dialog for inserting, editing and re-using MediaWiki references.
 *
 * @constructor
 * @extends ve.ui.NodeDialog
 * @param {Object} [config] Configuration options
 */
ve.ui.MWReferenceDialog = function VeUiMWReferenceDialog( config ) {
	// Parent constructor
	ve.ui.MWReferenceDialog.super.call( this, config );

	// Properties
	this.reuseReference = false;
};

/* Inheritance */

OO.inheritClass( ve.ui.MWReferenceDialog, ve.ui.NodeDialog );

/* Static Properties */

ve.ui.MWReferenceDialog.static.name = 'reference';

ve.ui.MWReferenceDialog.static.title =
	OO.ui.deferMsg( 'cite-ve-dialog-reference-title' );

ve.ui.MWReferenceDialog.static.actions = [
	{
		action: 'done',
		label: OO.ui.deferMsg( 'visualeditor-dialog-action-apply' ),
		flags: [ 'progressive', 'primary' ],
		modes: 'edit'
	},
	{
		action: 'insert',
		label: OO.ui.deferMsg( 'visualeditor-dialog-action-insert' ),
		flags: [ 'progressive', 'primary' ],
		modes: 'insert'
	},
	{
		label: OO.ui.deferMsg( 'visualeditor-dialog-action-cancel' ),
		flags: [ 'safe', 'close' ],
		modes: [ 'readonly', 'insert', 'edit', 'insert-select' ]
	}
];

ve.ui.MWReferenceDialog.static.modelClasses = [ ve.dm.MWReferenceNode ];

/* Methods */
/**
 * Handle ve.ui.MWReferenceEditPanel#change events
 *
 * @param {Object} change
 * @param {boolean} [change.isModified] If changes to the original content or values have been made
 * @param {boolean} [change.hasContent] If there's non empty content set
 */
ve.ui.MWReferenceDialog.prototype.onEditPanelInputChange = function ( change ) {
	this.actions.setAbilities( {
		done: change.isModified,
		insert: change.hasContent
	} );

	if ( !this.trackedInputChange ) {
		ve.track( 'activity.' + this.constructor.static.name, { action: 'input' } );
		this.trackedInputChange = true;
	}
};

/**
 * Handle search results ref reuse events.
 *
 * @param {ve.dm.MWReferenceModel} ref
 */
ve.ui.MWReferenceDialog.prototype.onReuseSearchResultsReuse = function ( ref ) {
	if ( this.selectedNode instanceof ve.dm.MWReferenceNode ) {
		this.getFragment().removeContent();
		this.selectedNode = null;
	}

	this.insertReference( ref );

	ve.track( 'activity.' + this.constructor.static.name, { action: 'reuse-choose' } );

	this.close( { action: 'insert' } );
};

/**
 * Handle search results popup menu extends events.
 *
 * @param {ve.dm.MWReferenceModel} originalRef
 */
ve.ui.MWReferenceDialog.prototype.onReuseSearchResultsExtends = function ( originalRef ) {
	const newRef = new ve.dm.MWReferenceModel( this.getFragment().getDocument() );
	newRef.extendsRef = originalRef.getListKey();
	newRef.group = originalRef.getGroup();

	this.actions.setMode( 'insert' );
	this.panels.setItem( this.editPanel );
	this.title.setLabel( ve.msg( 'cite-ve-dialog-reference-title-add-details' ) );

	const docRefs = ve.dm.MWDocumentReferences.static.refsForDoc(
		this.getFragment().getDocument()
	);
	this.editPanel.setDocumentReferences( docRefs );

	this.actions.setAbilities( { insert: false } );

	this.editPanel.setReferenceForEditing( newRef );
	this.editPanel.setReadOnly( this.isReadOnly() );

	this.trackedInputChange = false;
};

/**
 * @override
 */
ve.ui.MWReferenceDialog.prototype.getReadyProcess = function ( data ) {
	return ve.ui.MWReferenceDialog.super.prototype.getReadyProcess.call( this, data )
		.next( () => {
			if ( this.reuseReference ) {
				this.reuseSearch.getQuery().focus().select();
			} else {
				this.editPanel.focus();
			}
		} );
};

/**
 * @override
 */
ve.ui.MWReferenceDialog.prototype.getBodyHeight = function () {
	// Clamp value to between 300 and 400px height, preferring the actual height if available
	return Math.min(
		400,
		Math.max(
			300,
			Math.ceil( this.panels.getCurrentItem().$element[ 0 ].scrollHeight )
		)
	);
};

/**
 * @override
 */
ve.ui.MWReferenceDialog.prototype.initialize = function () {
	// Parent method
	ve.ui.MWReferenceDialog.super.prototype.initialize.call( this );

	// Properties
	this.panels = new OO.ui.StackLayout();
	this.editPanel = new ve.ui.MWReferenceEditPanel( { $overlay: this.$overlay } );
	this.reuseSearchPanel = new OO.ui.PanelLayout();

	this.reuseSearch = new ve.ui.MWReferenceSearchWidget( { $overlay: this.$overlay } );

	// Events
	this.reuseSearch.connect( this, {
		reuse: 'onReuseSearchResultsReuse',
		extends: 'onReuseSearchResultsExtends'
	} );
	this.editPanel.connect( this, { change: 'onEditPanelInputChange' } );

	// Initialization
	this.$content.addClass( 've-ui-mwReferenceDialog' );

	this.panels.addItems( [ this.editPanel, this.reuseSearchPanel ] );
	this.reuseSearchPanel.$element.append( this.reuseSearch.$element );
	this.$body.append( this.panels.$element );
};

/**
 * Switches dialog to use existing reference mode.
 */
ve.ui.MWReferenceDialog.prototype.openReusePanel = function () {
	this.actions.setMode( 'insert-select' );
	this.reuseSearch.buildIndex();
	this.panels.setItem( this.reuseSearchPanel );

	// https://phabricator.wikimedia.org/T362347
	ve.track( 'activity.' + this.constructor.static.name, { action: 'dialog-open-reuse' } );
};

/**
 * Insert a reference at the end of the selection, could also be a reuse of an exising reference
 *
 * @private
 * @param {ve.dm.MWReferenceModel} ref
 */
ve.ui.MWReferenceDialog.prototype.insertReference = function ( ref ) {
	const surfaceModel = this.getFragment().getSurface();

	if ( !ref.findInternalItem( surfaceModel ) ) {
		ref.insertInternalItem( surfaceModel );
	}
	// Collapse returns a new fragment, so update this.fragment
	this.fragment = this.getFragment().collapseToEnd();
	ref.insertReferenceNode( this.getFragment() );
};

/**
 * @override
 */
ve.ui.MWReferenceDialog.prototype.getActionProcess = function ( action ) {
	if ( action === 'insert' || action === 'done' ) {
		return new OO.ui.Process( () => {
			const ref = this.editPanel.getReferenceFromEditing();

			if ( !( this.selectedNode instanceof ve.dm.MWReferenceNode ) ) {
				this.insertReference( ref );
			}

			ref.updateInternalItem( this.getFragment().getSurface() );

			this.close( { action: action } );
		} );
	}
	return ve.ui.MWReferenceDialog.super.prototype.getActionProcess.call( this, action );
};

/**
 * @override
 * @param {Object} [data] Setup data
 * @param {boolean} [data.reuseReference=false] Open the dialog in "use existing reference" mode
 * @param {ve.dm.MWReferenceModel} [data.createSubRef] Open the dialog to add additional details to a reuse
 */
ve.ui.MWReferenceDialog.prototype.getSetupProcess = function ( data ) {
	data = data || {};
	return ve.ui.MWReferenceDialog.super.prototype.getSetupProcess.call( this, data )
		.next( () => {
			this.reuseReference = !!data.reuseReference;
			if ( this.reuseReference ) {
				this.reuseSearch.setInternalList( this.getFragment().getDocument().getInternalList() );
				this.openReusePanel();
			} else if ( data.createSubRef ) {
				if ( this.selectedNode instanceof ve.dm.MWReferenceNode &&
					this.selectedNode.getAttribute( 'placeholder' ) ) {
					// remove the placeholder node from Citoid
					this.getFragment().removeContent();

				}
				// we never want to edit an existing node here
				this.selectedNode = null;
				this.onReuseSearchResultsExtends( data.createSubRef );
			} else {
				this.panels.setItem( this.editPanel );
				const docRefs = ve.dm.MWDocumentReferences.static.refsForDoc(
					this.getFragment().getDocument()
				);
				this.editPanel.setDocumentReferences( docRefs );

				let ref;
				if ( this.selectedNode instanceof ve.dm.MWReferenceNode ) {
					// edit an existing reference
					ref = ve.dm.MWReferenceModel.static.newFromReferenceNode( this.selectedNode );
					if ( ref.extendsRef ) {
						this.title.setLabel( ve.msg( 'cite-ve-dialog-reference-title-edit-details' ) );
					}
					this.actions.setAbilities( { done: false } );
				} else {
					// create a new reference
					ref = new ve.dm.MWReferenceModel( this.getFragment().getDocument() );
					this.actions.setAbilities( { insert: false } );
				}
				this.editPanel.setReferenceForEditing( ref );
				this.editPanel.setReadOnly( this.isReadOnly() );
			}

			this.trackedInputChange = false;
		} );
};

/**
 * @override
 */
ve.ui.MWReferenceDialog.prototype.getTeardownProcess = function ( data ) {
	return ve.ui.MWReferenceDialog.super.prototype.getTeardownProcess.call( this, data )
		.first( () => {
			this.editPanel.clear();
			this.reuseSearch.clearSearch();
		} );
};

/* Registration */

ve.ui.windowFactory.register( ve.ui.MWReferenceDialog );
