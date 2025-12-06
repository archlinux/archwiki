ve.dm.modelRegistry.register( require( './ve.dm.MWReferenceNode.js' ) );
ve.dm.modelRegistry.register( require( './ve.dm.MWReferencesListNode.js' ) );

ve.ce.nodeFactory.register( require( './ve.ce.MWReferenceNode.js' ) );
ve.ce.nodeFactory.register( require( './ve.ce.MWReferencesListNode.js' ) );

ve.ui.windowFactory.register( require( './ve.ui.MWCitationDialog.js' ) );
ve.ui.windowFactory.register( require( './ve.ui.MWReferencesListDialog.js' ) );
ve.ui.windowFactory.register( require( './ve.ui.MWReferenceDialog.js' ) );
ve.ui.windowFactory.register( require( './ve.ui.MWSubReferenceHelpDialog.js' ) );

ve.ui.toolFactory.register( require( './ve.ui.MWReferenceDialogTool.js' ) );
ve.ui.toolFactory.register( require( './ve.ui.MWUseExistingReferenceDialogTool.js' ) );
ve.ui.toolFactory.register( require( './ve.ui.MWReferencesListDialogTool.js' ) );

ve.ui.contextItemFactory.register( require( './ve.ui.MWReferenceContextItem.js' ) );
ve.ui.contextItemFactory.register( require( './ve.ui.MWReferencesListContextItem.js' ) );
ve.ui.contextItemFactory.register( require( './ve.ui.MWCitationNeededContextItem.js' ) );

ve.ui.actionFactory.register( require( './ve.ui.MWCitationAction.js' ) );

const MWUseExistingReferenceCommand = require( './ve.ui.MWUseExistingReferenceCommand.js' );
const MWReferencesListCommand = require( './ve.ui.MWReferencesListCommand.js' );
ve.ui.commandRegistry.register( new MWUseExistingReferenceCommand() );
ve.ui.commandRegistry.register( new MWReferencesListCommand() );
ve.ui.commandRegistry.register( new ve.ui.Command(
	'reference', 'window', 'open',
	{ args: [ 'reference' ], supportedSelections: [ 'linear' ] }
) );

/* If Citoid is installed these will be overridden */
ve.ui.sequenceRegistry.register( new ve.ui.Sequence(
	'wikitextRef', 'reference', '<ref', 4
) );

ve.ui.triggerRegistry.register( 'reference', {
	mac: new ve.ui.Trigger( 'cmd+shift+k' ),
	pc: new ve.ui.Trigger( 'ctrl+shift+k' )
} );

ve.ui.commandHelpRegistry.register( 'insert', 'ref', {
	trigger: 'reference',
	sequences: [ 'wikitextRef' ],
	label: OO.ui.deferMsg( 'cite-ve-dialog-reference-title' )
} );

ve.ui.mwWikitextTransferRegistry.register( 'reference', /<ref[^>]*>/ );

ve.ui.HelpCompletionAction.static.toolGroups.cite = { mergeWith: 'insert' };

// TODO: Remove after Citoid and ContentTranslation are updated to not use this any more
ve.ui.mwCitationTools = require( './ve.ui.MWCitationTools.json' );
// TODO: We could merge the two init files. Is this worth it?
require( './ve.ui.MWReference.init.js' );

const data = require( './ve.ui.contentLanguage.json' );
for ( const languageCode in data ) {
	mw.language.setData( languageCode, data[ languageCode ] );
}
