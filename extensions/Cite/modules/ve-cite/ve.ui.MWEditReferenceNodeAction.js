'use strict';

/*!
 * VisualEditor UserInterface MediaWiki MWEditReferenceNodeAction class.
 *
 * @copyright 2016 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

/**
 * Edit reference action. Executes the commands that open the {@link ve.ui.MWReferenceDialog}
 * or {@link ve.ui.MWCitationDialog} to edit a reference.
 *
 * @class
 * @extends ve.ui.Action
 * @constructor
 * @param {ve.ui.Surface} surface Surface to act on
 * @param {string} [source]
 */
ve.ui.MWEditReferenceNodeAction = function VeUiMWEditReferenceNodeAction() {
	// Parent constructor
	ve.ui.MWEditReferenceNodeAction.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWEditReferenceNodeAction, ve.ui.Action );

/* Static Properties */

ve.ui.MWEditReferenceNodeAction.static.name = 'editNode';

ve.ui.MWEditReferenceNodeAction.static.methods = [ 'execute' ];

/* Methods */

/**
 * Depending on the type of reference, compose the required arguments for the command and call it.
 *
 * @param {ve.dm.MWReferenceModel} ref reference to edit
 */
ve.ui.MWEditReferenceNodeAction.prototype.execute = function ( ref ) {
	const internalItem = ref.findInternalItem( this.surface.getModel() );

	if ( !internalItem ) {
		return;
	}

	const commandName = this.getCommandNameFromInternalItem( internalItem );
	const refCommand = ve.ui.commandRegistry.lookup( commandName );

	if ( !refCommand ) {
		return;
	}

	const additionalWindowData = {
		refToEdit: ref
	};

	if ( commandName === 'reference' ) {
		refCommand.execute(
			this.surface,
			// Arguments for calling ve.ui.MWReferenceDialog.getSetupProcess()
			[ 'reference', additionalWindowData ],
			this.source
		);
	} else {
		//  Arguments for calling ve.ui.MWCitationDialog.getSetupProcess()
		const newArgs = ve.copy( refCommand.args );
		ve.extendObject( newArgs[ 0 ], additionalWindowData );
		refCommand.execute( this.surface, newArgs, this.source );
	}
};

/**
 * Gets the name of a command that works for the content of an InternalItem by
 * iterating over MediaWiki:Cite-tool-definition.json entries and see if any
 * template fits.
 *
 * @param {ve.dm.InternalItemNode} internalItem
 * @return {string}
 * @private
 */
ve.ui.MWEditReferenceNodeAction.prototype.getCommandNameFromInternalItem = function ( internalItem ) {
	const matchingToolDefinition = ve.ui.mwCitationTools.find( ( toolDefinition ) => ve.ui.MWCitationDialog
		.static.getTransclusionNodeWithTemplate(
			internalItem, toolDefinition.template
		) );

	return matchingToolDefinition ?
		ve.ui.MWCitationDialogTool.static.namePrefix + matchingToolDefinition.name :
		'reference';
};

module.exports = ve.ui.MWEditReferenceNodeAction;
