/**
 * Edit check to detect citation needed templates
 *
 * @class
 * @extends mw.editcheck.BaseEditCheck
 *
 * @constructor
 * @param {mw.editcheck.Controller} controller
 * @param {Object} [config]
 * @param {boolean} [includeSuggestions=false]
 */
mw.editcheck.CitationNeededEditCheck = function MWCitationNeededEditCheck() {
	// Parent constructor
	mw.editcheck.CitationNeededEditCheck.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( mw.editcheck.CitationNeededEditCheck, mw.editcheck.BaseEditCheck );

/* Static properties */

mw.editcheck.CitationNeededEditCheck.static.defaultConfig = ve.extendObject( {}, mw.editcheck.BaseEditCheck.static.defaultConfig, {
	showAsCheck: false,
	showAsSuggestion: false
} );

mw.editcheck.CitationNeededEditCheck.static.title = OO.ui.deferMsg( 'cite-ve-citationneeded-title' );

mw.editcheck.CitationNeededEditCheck.static.description = ve.deferJQueryMsg( 'cite-ve-citationneeded-description' );

mw.editcheck.CitationNeededEditCheck.static.name = 'citationNeeded';

mw.editcheck.CitationNeededEditCheck.static.choices = [
	{
		action: 'add',
		label: OO.ui.deferMsg( 'cite-ve-citationneeded-button' )
	},
	{
		action: 'dismiss',
		label: OO.ui.deferMsg( 'ooui-dialog-process-dismiss' )
	}
];

/* Methods */

mw.editcheck.CitationNeededEditCheck.prototype.getCitationNeededRanges = function ( documentModel ) {
	return this.getAddedNodes( documentModel, 'mwTransclusionInline' )
		.filter( ( node ) => ve.ui.MWCitationNeededContextItem.static.isCompatibleWith( node ) )
		.map( ( node ) => node.getOuterRange() );
};

mw.editcheck.CitationNeededEditCheck.prototype.onDocumentChange = function ( surfaceModel ) {
	const documentModel = surfaceModel.getDocument();
	return this.getCitationNeededRanges( documentModel ).map( ( range ) => {
		if ( this.isDismissedRange( range ) ) {
			return null;
		}
		// TODO: The context has a more complex description that includes the
		// date and reason parameters if they are available, but pulling that
		// in would require refactoring of ve.ui.MWCitationNeededContextItem.
		return new mw.editcheck.EditCheckAction( {
			fragments: [ surfaceModel.getLinearFragment( range ) ],
			check: this
		} );
	} );
};

mw.editcheck.CitationNeededEditCheck.prototype.act = function ( choice, action, surface ) {
	if ( choice === 'add' ) {
		action.fragments[ 0 ].select();
		const node = action.fragments[ 0 ].getSelectedNode();
		const context = new ve.ui.MWCitationNeededContextItem( surface.getContext(), node );
		context.onAddClick();
		return;
	}

	// Parent method
	return mw.editcheck.CitationNeededEditCheck.super.prototype.act.apply( this, arguments );
};

/* Registration */

mw.editcheck.editCheckFactory.register( mw.editcheck.CitationNeededEditCheck );
