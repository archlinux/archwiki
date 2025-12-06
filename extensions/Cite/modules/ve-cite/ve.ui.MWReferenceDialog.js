'use strict';

/*!
 * VisualEditor UserInterface MediaWiki MWReferenceDialog class.
 *
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

const MWDocumentReferences = require( './ve.dm.MWDocumentReferences.js' );
const MWReferenceModel = require( './ve.dm.MWReferenceModel.js' );
const MWReferenceNode = require( './ve.dm.MWReferenceNode.js' );
const MWReferenceEditPanel = require( './ve.ui.MWReferenceEditPanel.js' );
const MWReferenceSearchWidget = require( './ve.ui.MWReferenceSearchWidget.js' );

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

ve.ui.MWReferenceDialog.static.modelClasses = [ MWReferenceNode ];

/* Methods */
/**
 * Handle {@link MWReferenceEditPanel#change} events
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
 * @param {MWReferenceModel} ref
 */
ve.ui.MWReferenceDialog.prototype.onReuseSearchResultsReuse = function ( ref ) {
	if ( this.selectedNode instanceof MWReferenceNode ) {
		this.getFragment().removeContent();
		this.selectedNode = null;
	}

	// Special case for sub-references: create a copy so both can be edited independently
	if ( ref.isSubRef() ) {
		// Phabricator T396734
		ve.track( 'activity.subReference', { action: 'reuse-choose-subref' } );
		ref = MWReferenceModel.static.copySubReference( ref, this.getFragment().getDocument() );
	}

	// Collapse returns a new fragment, so update this.fragment
	this.fragment = this.getFragment().collapseToEnd();
	ref.insertIntoFragment( this.getFragment() );

	ve.track( 'activity.' + this.constructor.static.name, { action: 'reuse-choose' } );

	this.close( { action: 'insert' } );
};

ve.ui.MWReferenceDialog.prototype.setCreateSubRefPanel = function ( mainRef ) {
	const newRef = new MWReferenceModel( this.getFragment().getDocument() );
	newRef.mainRefKey = mainRef.getListKey();
	newRef.group = mainRef.getGroup();

	this.title.setLabel( ve.msg( 'cite-ve-dialog-reference-title-details' ) );
	this.panels.setItem( this.editPanel );

	const docRefs = MWDocumentReferences.static.refsForDoc(
		this.getFragment().getDocument()
	);
	this.editPanel.setDocumentReferences( docRefs );

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
	this.editPanel = new MWReferenceEditPanel( { $overlay: this.$overlay } );
	this.reuseSearchPanel = new OO.ui.PanelLayout();

	this.reuseSearch = new MWReferenceSearchWidget( { $overlay: this.$overlay } );

	// Events
	this.reuseSearch.connect( this, { reuse: 'onReuseSearchResultsReuse' } );
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
 * @override
 */
ve.ui.MWReferenceDialog.prototype.getActionProcess = function ( action ) {
	if ( action === 'insert' || action === 'done' ) {
		return new OO.ui.Process( () => {
			let ref = this.editPanel.getReferenceFromEditing();
			const nodeGroup = this.getFragment().getDocument()
				.getInternalList().getNodeGroup( 'mwReference/' + ref.group );
			if ( !( this.selectedNode instanceof MWReferenceNode ) ) {
				// Collapse returns a new fragment, so update this.fragment
				this.fragment = this.getFragment().collapseToEnd();
				ref.insertIntoFragment( this.getFragment() );
			} else if ( this.createSubRefMode ) {
				// We're creating a new sub-ref by replacing a main ref
				// make sure there's a synth main ref to save the main body
				const mainNodes = nodeGroup.getAllReuses( ref.mainRefKey ) || [];
				const foundExistingSynthMain = mainNodes.some(
					( node ) => ve.getProp( node.getAttribute( 'mw' ), 'isSyntheticMainRef' ) );
				if ( !foundExistingSynthMain && mainNodes.length ) {
					const mainNodeToCopy = mainNodes
						.find( ( node ) => node.getAttribute( 'refListItemId' ) ) || mainNodes[ 0 ];
					mainNodeToCopy.copySyntheticRefIntoReferencesList( this.getFragment().getSurface() );
				}

				// Check if the main node we're replacing was keeping the content
				const contentsUsed = this.selectedNode.getAttribute( 'contentsUsed' );

				// When creating a sub-ref we're always replacing the selected node
				this.getFragment().removeContent();
				// Collapse returns a new fragment, so update this.fragment
				this.fragment = this.getFragment().collapseToEnd();
				ref.insertIntoFragment( this.getFragment(), contentsUsed );

				// Phabricator T396734
				ve.track( 'activity.subReference', { action: 'dialog-done-add-details' } );
			} else {
				if ( ref.isSubRef() ) {
					// We don't want to edit all sub-ref reuses. If there's one here we need
					// to generate new keys and insert the sub-ref as new node to split it.
					const subRefReuses = nodeGroup.getAllReuses( ref.listKey ) || [];
					if ( subRefReuses.length > 1 ) {
						ref = MWReferenceModel.static.copySubReference( ref, this.getFragment().getDocument() );
						this.getFragment().removeContent();
						ref.insertIntoFragment( this.getFragment() );
					}
					// Phabricator T396734
					ve.track( 'activity.subReference', { action: 'dialog-done-edit-details' } );
				}
				ref.updateInternalItem( this.getFragment().getSurface() );
			}
			this.close( { action } );
		} );
	}
	return ve.ui.MWReferenceDialog.super.prototype.getActionProcess.call( this, action );
};

/**
 * @override
 * @param {Object} [data] Setup data
 * @param {boolean} [data.reuseReference=false] Open the dialog in "use existing reference" mode
 * @param {MWReferenceModel} [data.createSubRef] Open the dialog to add additional details to a reuse
 */
ve.ui.MWReferenceDialog.prototype.getSetupProcess = function ( data ) {
	data = data || {};
	return ve.ui.MWReferenceDialog.super.prototype.getSetupProcess.call( this, data )
		.next( () => {
			this.createSubRefMode = false;
			this.reuseReference = !!data.reuseReference;
			if ( this.reuseReference ) {
				this.reuseSearch.setInternalList( this.getFragment().getDocument().getInternalList() );
				this.openReusePanel();
			} else if ( data.createSubRef ) {
				this.actions.setMode( 'edit' );
				this.actions.setAbilities( { done: false } );
				this.setCreateSubRefPanel( data.createSubRef );
				this.createSubRefMode = true;
			} else {
				this.panels.setItem( this.editPanel );
				const docRefs = MWDocumentReferences.static.refsForDoc(
					this.getFragment().getDocument()
				);
				this.editPanel.setDocumentReferences( docRefs );

				let ref;
				if ( this.selectedNode instanceof MWReferenceNode ) {
					// edit an existing reference
					ref = MWReferenceModel.static.newFromReferenceNode( this.selectedNode );
					if ( ref.isSubRef() ) {
						this.title.setLabel( ve.msg( 'cite-ve-dialog-reference-title-details' ) );
					}
					this.actions.setAbilities( { done: false } );
				} else {
					// create a new reference
					ref = new MWReferenceModel( this.getFragment().getDocument() );
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

			// Phabricator T396734
			if ( data === undefined ) {
				if ( this.createSubRefMode ) {
					ve.track( 'activity.subReference', {
						action: 'dialog-abort-add-details'
					} );
				} else {
					const ref = this.editPanel && this.editPanel.getReferenceFromEditing();
					if ( ref && ref.isSubRef() ) {
						ve.track( 'activity.subReference', {
							action: 'dialog-abort-edit-details'
						} );
					}
				}
			}

			this.editPanel.clear();
			this.reuseSearch.clearSearch();
		} );
};

module.exports = ve.ui.MWReferenceDialog;
