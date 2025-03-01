'use strict';

/*
 * VisualEditor user interface MWCitationDialog class.
 *
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

/**
 * Dialog for inserting and editing MediaWiki citations that use the templates that are set up for
 * the VisualEditor citation tool.
 *
 * @constructor
 * @extends ve.ui.MWTemplateDialog
 * @param {Object} [config] Configuration options
 */
ve.ui.MWCitationDialog = function VeUiMWCitationDialog( config ) {
	// Parent constructor
	ve.ui.MWCitationDialog.super.call( this, config );

	// Properties
	this.referenceModel = null;
	this.referenceNode = null;
	this.inDialog = '';
};

/* Inheritance */

OO.inheritClass( ve.ui.MWCitationDialog, ve.ui.MWTransclusionDialog );

/* Static Properties */

ve.ui.MWCitationDialog.static.name = 'cite';

/* Methods */

/**
 * Get the reference node to be edited.
 *
 * @return {ve.dm.MWReferenceNode|null} Reference node to be edited, null if none exists
 */
ve.ui.MWCitationDialog.prototype.getReferenceNode = function () {
	const selectedNode = this.getFragment().getSelectedNode();

	if ( selectedNode instanceof ve.dm.MWReferenceNode ) {
		return selectedNode;
	}

	return null;
};

/**
 * @override
 */
ve.ui.MWCitationDialog.prototype.getSelectedNode = function () {
	const referenceNode = this.getReferenceNode();

	if ( referenceNode ) {
		const branches = referenceNode.getInternalItem().getChildren();
		const leaves = branches &&
			branches.length === 1 &&
			branches[ 0 ].canContainContent() &&
			branches[ 0 ].getChildren();
		const transclusionNode = leaves &&
			leaves.length === 1 &&
			leaves[ 0 ] instanceof ve.dm.MWTransclusionNode &&
			leaves[ 0 ];

		// Only use the selected node if it is the same template as this dialog expects
		if ( transclusionNode && transclusionNode.isSingleTemplate( this.citationTemplate ) ) {
			return transclusionNode;
		}
	}

	return null;
};

/**
 * @override
 */
ve.ui.MWCitationDialog.prototype.initialize = function ( data ) {
	// Parent method
	ve.ui.MWCitationDialog.super.prototype.initialize.call( this, data );

	// HACK: Use the same styling as single-mode transclusion dialog - this should be generalized
	this.$content.addClass( 've-ui-mwTransclusionDialog-single' );

	this.$content.on( 'change', this.onInputChange.bind( this ) );
};

/**
 * @override
 */
ve.ui.MWCitationDialog.prototype.getSetupProcess = function ( data ) {
	return ve.ui.MWCitationDialog.super.prototype.getSetupProcess.call( this, data )
		.first( () => {
			data = data || {};
			this.inDialog = data.inDialog;
			this.citationTemplate = data.template;
			this.citationTitle = data.title;

			this.trackedCitationInputChange = false;
		} )
		.next( () => {
			this.updateTitle();

			// Initialization
			this.referenceNode = this.getReferenceNode();
			if ( this.referenceNode ) {
				this.referenceModel = ve.dm.MWReferenceModel.static.newFromReferenceNode(
					this.referenceNode
				);
			}
		} );
};

/**
 * @override
 */
ve.ui.MWCitationDialog.prototype.updateTitle = function () {
	if ( this.citationTitle ) {
		this.title.setLabel( this.citationTitle );
	} else {
		// Parent method
		ve.ui.MWCitationDialog.super.prototype.updateTitle.call( this );
	}
};

/**
 * @override
 */
ve.ui.MWCitationDialog.prototype.setApplicableStatus = function () {
	ve.ui.MWCitationDialog.super.prototype.setApplicableStatus.call( this );
	// Parent method disables 'done' if no changes were made (this is okay for us), and
	// disables 'insert' if transclusion is empty (but it is never empty in our case).
	// Instead, disable 'insert' if no parameters were added.
	this.actions.setAbilities( { insert: this.transclusionModel.containsValuableData() } );
};

/**
 * @override
 */
ve.ui.MWCitationDialog.prototype.getActionProcess = function ( action ) {
	if (
		this.inDialog !== 'reference' &&
		( action === 'done' || action === 'insert' )
	) {
		return new OO.ui.Process( () => {
			const deferred = $.Deferred();
			this.checkRequiredParameters().done( () => {
				const surfaceModel = this.getFragment().getSurface();
				const doc = surfaceModel.getDocument();

				// We had a reference, but no template node (or wrong kind of template node)
				if ( this.referenceModel && !this.selectedNode ) {
					const refDoc = this.referenceModel.getDocument();
					// Empty the existing reference, whatever it contained. This allows
					// the dialog to be used for arbitrary references (to replace their
					// contents with a citation).
					refDoc.commit(
						ve.dm.TransactionBuilder.static
							.newFromRemoval( refDoc, refDoc.getDocumentRange(), true )
					);
				}

				if ( !this.referenceModel ) {
					// Collapse returns a new fragment, so update this.fragment
					this.fragment = this.getFragment().collapseToEnd();
					this.referenceModel = new ve.dm.MWReferenceModel( doc );
					this.referenceModel.insertInternalItem( surfaceModel );
					this.referenceModel.insertReferenceNode( this.getFragment() );
				}

				const item = this.referenceModel.findInternalItem( surfaceModel );
				if ( item ) {
					if ( this.selectedNode ) {
						this.transclusionModel.updateTransclusionNode(
							surfaceModel, this.selectedNode
						);
					} else if ( this.transclusionModel.getPlainObject() !== null ) {
						this.transclusionModel.insertTransclusionNode(
							// HACK: This is trying to place the cursor inside the first
							// content branch node but this theoretically not a safe
							// assumption - in practice, the citation dialog will only reach
							// this code if we are inserting (not updating) a transclusion, so
							// the referenceModel will have already initialized the internal
							// node with a paragraph - getting the range of the item covers
							// the entire paragraph so we have to get the range of it's first
							// (and empty) child
							this.getFragment().clone(
								new ve.dm.LinearSelection( item.getChildren()[ 0 ].getRange() )
							),
							'inline'
						);
					}
				}

				// HACK: Scorch the earth - this is only needed because without it, the
				// references list won't re-render properly, and can be removed once
				// someone fixes that
				this.referenceModel.setDocument(
					doc.cloneFromRange(
						doc.getInternalList().getItemNode( this.referenceModel.getListIndex() ).getRange()
					)
				);
				this.referenceModel.updateInternalItem( surfaceModel );

				this.close( { action: action } );
			} ).always( deferred.resolve );

			return deferred;
		} );
	}

	// Parent method
	return ve.ui.MWCitationDialog.super.prototype.getActionProcess.call( this, action );
};

/**
 * @override
 */
ve.ui.MWCitationDialog.prototype.getTeardownProcess = function ( data ) {
	return ve.ui.MWCitationDialog.super.prototype.getTeardownProcess.call( this, data )
		.first( () => {
			// Cleanup
			this.referenceModel = null;
			this.referenceNode = null;
		} );
};

/**
 * Handle change events on the transclusion inputs
 *
 * @param {jQuery.Event} ev The browser event
 */
ve.ui.MWCitationDialog.prototype.onInputChange = function () {
	if ( !this.trackedCitationInputChange ) {
		ve.track( 'activity.' + this.constructor.static.name, { action: 'manual-template-input' } );
		this.trackedCitationInputChange = true;
	}
};

/* Registration */

ve.ui.windowFactory.register( ve.ui.MWCitationDialog );
